<?php
/**
 * Крон: генерация sitemap.xml для Google и Яндекса.
 * Запуск: раз в сутки через crontab (и вручную после деплоя):
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/sitemap_generate.php
 *
 * Пишет в корень сайта:
 *   sitemap.xml            — индекс (на него ссылается robots.txt);
 *   sitemap-pages.xml      — информационные страницы, магазины, услуги;
 *   sitemap-catalog-N.xml  — разделы и товары каталога (до 45 000 адресов в файле).
 *
 * В каталог попадают только активные товары с остатком > 0 — ровно то, что
 * показывает сайт (catalog/index.php фильтрует CATALOG_QUANTITY >= 1), — и
 * разделы, в которых такие товары есть. Адреса — канонические: разделы
 * /catalog/{ветка}/{код}/, товары через catalogElementUrl() из init.php.
 *
 * Стандартный генератор модуля «Поисковая оптимизация» Битрикса запускать
 * не нужно: он не знает о разделении каталога на ветки и перезапишет
 * sitemap.xml неправильными адресами (так появились старые sitemap-iblock-*.xml).
 */

$docRoot = realpath(__DIR__ . '/../../..');
$logDir = $docRoot . '/upload/logs';
$logFile = $logDir . '/sitemap_generate_' . date('Y-m-d') . '.log';

function slog(string $msg): void {
    global $logFile, $logDir;
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    if (is_dir($logDir)) {
        file_put_contents($logFile, $line, FILE_APPEND);
    }
    echo $line;
}

// Тот же приём бутстрапа, что в payment_hold_sweep.php: session_start()
// внутри prolog_before.php падает, если до него в поток уже что-то выведено.
ob_start();
try {
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    define('NO_KEEP_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);
    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
    CModule::IncludeModule('iblock');
    CModule::IncludeModule('catalog');
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    slog('Bitrix bootstrap failed: ' . $e->getMessage());
    exit(1);
}

const SITEMAP_HOST = 'https://liderws.ru';
const SITEMAP_CHUNK = 45000;

slog('=== sitemap_generate START ===');

/** @return string W3C-дата для <lastmod> из даты Битрикса или unix-времени. */
function sitemapDate($value): string {
    if (is_int($value)) {
        return date('c', $value);
    }
    $ts = $value ? MakeTimeStamp($value) : false;
    return date('c', $ts ?: time());
}

function sitemapUrlTag(string $path, string $lastmod): string {
    return '<url><loc>' . htmlspecialchars(SITEMAP_HOST . $path, ENT_XML1) . '</loc><lastmod>' . $lastmod . '</lastmod></url>';
}

function writeSitemapFile(string $docRoot, string $name, string $body): void {
    $tmp = $docRoot . '/' . $name . '.tmp';
    file_put_contents($tmp, '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $body);
    rename($tmp, $docRoot . '/' . $name);
}

// --- Информационные страницы ---
// Только то, на что ведёт навигация нового шаблона; lastmod — по файлу страницы.
$pageFiles = [
    '/'                                => 'index.php',
    '/catalog/'                        => 'catalog/branches.php',
    '/podbor-po-vin/'                  => 'podbor-po-vin/index.php',
    '/service-parts/'                  => 'service-parts/index.php',
    '/autoservice/'                    => 'autoservice/index.php',
    '/shinomontazh/'                   => 'shinomontazh/index.php',
    '/about/'                          => 'about/index.php',
    '/contacts/'                       => 'contacts/index.php',
    '/klientam/delivery/'              => 'klientam/delivery/index.php',
    '/klientam/oplata/'                => 'klientam/oplata/index.php',
    '/klientam/garantiya-i-vozvrat/'   => 'klientam/garantiya-i-vozvrat/index.php',
    '/klientam/optovym-klientam/'      => 'klientam/optovym-klientam/index.php',
    '/rekvizity/'                      => 'rekvizity/index.php',
];
foreach (glob($docRoot . '/autoservice/*/index.php') as $serviceFile) {
    $pageFiles['/autoservice/' . basename(dirname($serviceFile)) . '/'] = substr($serviceFile, strlen($docRoot) + 1);
}
require_once $docRoot . '/local/php_interface/include/shop_locations.php';
foreach (getShopLocations() as $shop) {
    $pageFiles['/shop/' . $shop['id'] . '/'] = 'local/php_interface/include/shop_locations.php';
}

$pageTags = [];
foreach ($pageFiles as $path => $file) {
    $full = $docRoot . '/' . $file;
    if (!is_file($full)) {
        slog('skip page (no file): ' . $path);
        continue;
    }
    $pageTags[] = sitemapUrlTag($path, sitemapDate((int)filemtime($full)));
}

// --- Каталог ---
$catalogTags = [];
foreach (CATALOG_BRANCHES as $branch => $info) {
    $iblockId = (int)$info['id'];
    $prefix = '/catalog/' . $branch . '/';
    $root = resolveEffectiveRoot($iblockId);
    $hidden = array_flip($root['skippedIds']);

    // Все активные разделы ветки — для подъёма от раздела товара к предкам.
    $sections = [];
    $res = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y'], false, ['ID', 'CODE', 'IBLOCK_SECTION_ID', 'TIMESTAMP_X']);
    while ($row = $res->Fetch()) {
        $sections[(int)$row['ID']] = $row;
    }

    $branchLastmod = 0;
    $usedSections = [];
    $elementCount = 0;
    $res = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', '>=CATALOG_QUANTITY' => 1],
        false,
        false,
        ['ID', 'IBLOCK_ID', 'CODE', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL', 'TIMESTAMP_X']
    );
    while ($el = $res->GetNext()) {
        if ((string)$el['~CODE'] === '') {
            continue; // без символьного кода у товара нет ЧПУ-адреса
        }
        $ts = MakeTimeStamp($el['TIMESTAMP_X']) ?: time();
        $branchLastmod = max($branchLastmod, $ts);
        $catalogTags[] = sitemapUrlTag(catalogElementUrl($branch, $el), sitemapDate($ts));
        $elementCount++;

        // Раздел товара и все его предки — непустые, их тоже в карту.
        $sid = (int)$el['IBLOCK_SECTION_ID'];
        while ($sid > 0 && isset($sections[$sid]) && !isset($usedSections[$sid])) {
            $usedSections[$sid] = max($usedSections[$sid] ?? 0, $ts);
            $sid = (int)$sections[$sid]['IBLOCK_SECTION_ID'];
        }
    }

    $catalogTags[] = sitemapUrlTag($prefix, sitemapDate($branchLastmod ?: time()));
    $sectionCount = 0;
    foreach ($usedSections as $sid => $ts) {
        $section = $sections[$sid];
        if (isset($hidden[$sid]) || $sid === (int)$root['rootId'] || (string)$section['CODE'] === '') {
            continue; // технические обёртки 1С (см. resolveEffectiveRoot) своих страниц не имеют
        }
        $catalogTags[] = sitemapUrlTag($prefix . $section['CODE'] . '/', sitemapDate($ts));
        $sectionCount++;
    }
    slog(sprintf('branch %s: %d sections, %d products', $branch, $sectionCount, $elementCount));
}

// --- Запись файлов ---
$now = date('c');
$files = [];
writeSitemapFile($docRoot, 'sitemap-pages.xml',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode('', $pageTags) . '</urlset>');
$files[] = 'sitemap-pages.xml';

foreach (array_chunk($catalogTags, SITEMAP_CHUNK) as $i => $chunk) {
    $name = 'sitemap-catalog-' . ($i + 1) . '.xml';
    writeSitemapFile($docRoot, $name,
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode('', $chunk) . '</urlset>');
    $files[] = $name;
}

$index = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($files as $name) {
    $index .= '<sitemap><loc>' . SITEMAP_HOST . '/' . $name . '</loc><lastmod>' . $now . '</lastmod></sitemap>';
}
writeSitemapFile($docRoot, 'sitemap.xml', $index . '</sitemapindex>');

// Устаревшие файлы: старый генератор Битрикса (инфоблок 42) и лишние части каталога.
foreach (array_merge(glob($docRoot . '/sitemap-iblock-*.xml') ?: [], glob($docRoot . '/sitemap-files.xml') ?: [], glob($docRoot . '/sitemap-catalog-*.xml') ?: []) as $old) {
    if (!in_array(basename($old), $files, true)) {
        unlink($old);
        slog('removed stale ' . basename($old));
    }
}

slog(sprintf('done: %d pages, %d catalog urls, files: %s', count($pageTags), count($catalogTags), implode(', ', $files)));
