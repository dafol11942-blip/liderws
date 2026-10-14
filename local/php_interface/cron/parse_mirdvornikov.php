<?php
/**
 * Применяемость щёток стеклоочистителя с mirdvornikov.ru — прототип.
 *
 * Обход: /cars/ (марки) → /cars/{марка}/ (модели) → /cars/{марка-модель}/
 * (поколения) → /cars/{марка-модель}/{id}/ (страница поколения). Со страницы
 * поколения берутся:
 *   «водительский 650 мм + пассажирский 400 мм» → длины щёток;
 *   «Тип крепления Крючок 9x3»                  → крепление и признак «крючок»;
 *   ссылки /catalog/…-3397xxxxxx/               → артикулы Bosch из выдачи
 *     (задние — по «rear» в адресе или серии Hxxx), по ним ищем товары
 *     с некрючковым креплением; длина задней щётки текстом не указана.
 * Карточки и тексты сайта не сохраняются — только размеры и артикулы.
 *
 * Результат — HL-блоки WiperCars (поколение, размеры, крепление) и
 * WiperArticles (артикулы Bosch поколения); создаются при первом --apply.
 * Скачанные страницы кэшируются в /cache/mirdvornikov/ — повторный запуск
 * после обрыва продолжает с места остановки, разбор можно перезапускать без
 * сети (--refresh — скачать заново).
 *
 * Запуск (по умолчанию — пробный: печатает распознанное, в базу не пишет):
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/parse_mirdvornikov.php --brand=toyota
 * Параметры:
 *   --brand=toyota         только эта марка (slug из адреса /cars/toyota/)
 *   --model=toyota-rav4    только эта модель (slug из адреса)
 *   --apply                записать в HL-блоки
 *   --delay=3              пауза между запросами к сайту, сек
 *   --refresh              не брать страницы из кэша
 *   --proxy=http://h:port  ходить через прокси (если IP сервера заблокирован)
 */

$docRoot = realpath(__DIR__ . '/../../..');
$opts = [];
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $arg, $m)) $opts[$m[1]] = $m[2] ?? true;
}
$apply = isset($opts['apply']);

const MDV_BASE = 'https://www.mirdvornikov.ru';
const MDV_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

$cacheDir = $docRoot . '/cache/mirdvornikov';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
if (!is_file($cacheDir . '/.htaccess')) file_put_contents($cacheDir . '/.htaccess', "Deny from all\n");

if ($apply) {
    ob_start();
    try {
        $_SERVER['DOCUMENT_ROOT'] = $docRoot;
        define('NO_KEEP_STATISTIC', true);
        define('NOT_CHECK_PERMISSIONS', true);
        require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
        \Bitrix\Main\Loader::includeModule('highloadblock');
        ob_end_clean();
    } catch (\Throwable $e) {
        ob_end_clean();
        echo 'Bitrix bootstrap failed: ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

// Имя HL-блока => [таблица, поля [код, подпись, тип]]
const MDV_HL_BLOCKS = [
    'WiperCars' => ['b_hlbd_wiper_cars', [
        ['UF_GEN_ID',       'ID поколения (mirdvornikov)', 'integer'],
        ['UF_BRAND',        'Марка',                       'string'],
        ['UF_BRAND_CODE',   'Марка, код',                  'string'],
        ['UF_MODEL',        'Модель',                      'string'],
        ['UF_MODEL_CODE',   'Модель, код',                 'string'],
        ['UF_GEN_NAME',     'Поколение',                   'string'],
        ['UF_YEAR_FROM',    'Год от',                      'integer'],
        ['UF_YEAR_TO',      'Год до (0 — выпускается)',    'integer'],
        ['UF_DRIVER_MM',    'Водительская, мм',            'integer'],
        ['UF_PASSENGER_MM', 'Пассажирская, мм',            'integer'],
        ['UF_MOUNT',        'Тип крепления',               'string'],
        ['UF_IS_HOOK',      'Крючок (1/0)',                'integer'],
        ['UF_SIZES_RAW',    'Все варианты размеров',       'string'],
        ['UF_UPDATED',      'Обновлено',                   'string'],
    ]],
    'WiperArticles' => ['b_hlbd_wiper_articles', [
        ['UF_GEN_ID',   'ID поколения (mirdvornikov)', 'integer'],
        ['UF_ARTICLE',  'Артикул Bosch',               'string'],
        ['UF_POSITION', 'front / rear',                'string'],
        ['UF_SLUG',     'Адрес товара на источнике',   'string'],
    ]],
];

// ───────────── загрузка страниц ─────────────

function mdvFetch(string $path, array $opts, string $cacheDir): ?string
{
    static $lastRequest = 0.0;
    $file = $cacheDir . '/' . trim(preg_replace('~[^a-z0-9]+~i', '_', $path), '_') . '.html';
    if (!isset($opts['refresh']) && is_file($file) && filesize($file) > 0) return file_get_contents($file);

    $delay = (float)($opts['delay'] ?? 3);
    for ($try = 1; $try <= 3; $try++) {
        $wait = $lastRequest + $delay - microtime(true);
        if ($wait > 0) usleep((int)($wait * 1e6));
        $ch = curl_init(MDV_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => MDV_UA,
            CURLOPT_HTTPHEADER     => ['Accept-Language: ru-RU,ru;q=0.9'],
            CURLOPT_ENCODING       => '',
        ]);
        if (!empty($opts['proxy']) && $opts['proxy'] !== true) curl_setopt($ch, CURLOPT_PROXY, $opts['proxy']);
        $html = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $lastRequest = microtime(true);

        if ($code === 200 && $html !== false && $html !== '') {
            file_put_contents($file, $html);
            return $html;
        }
        if ($code === 404) {
            echo "  404 $path" . PHP_EOL;
            return null;
        }
        echo "  попытка $try: $path — HTTP $code $err" . PHP_EOL;
        sleep(10 * $try);
    }
    return null;
}

// ───────────── разбор ─────────────

/** Ссылки страницы: [[href, текст], …], текст без тегов и с одиночными пробелами. */
function mdvLinks(string $html): array
{
    preg_match_all('~<a\b[^>]*\bhref\s*=\s*["\']([^"\'#?]+)[^"\']*["\'][^>]*>(.*?)</a>~is', $html, $m, PREG_SET_ORDER);
    return array_map(fn($x) => [preg_replace('~^https?://(www\.)?mirdvornikov\.ru~i', '', $x[1]), mdvText($x[2])], $m);
}

function mdvText(string $html): string
{
    $html = preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $html);
    $text = html_entity_decode(strip_tags(preg_replace('~<~', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('~\s+~u', ' ', $text));
}

/** «09.2017-09.2023», «11.18→», «08.1996 - 09.2002» → [год от, год до | 0]. */
function mdvYears(string $text): array
{
    $year = fn(string $y) => strlen($y) === 2 ? ((int)$y < 50 ? 2000 : 1900) + (int)$y : (int)$y;
    if (!preg_match('~\b\d{2}\.(\d{4}|\d{2})\b\s*(?:[-–—]\s*(?:\d{2}\.(\d{4}|\d{2})\b)?|→)?~u', $text, $m)) return [0, 0];
    return [$year($m[1]), isset($m[2]) && $m[2] !== '' ? $year($m[2]) : 0];
}

/** Марки: [slug => название] из ссылок /cars/{slug}/. */
function mdvParseBrands(string $html): array
{
    $brands = [];
    foreach (mdvLinks($html) as [$href, $text]) {
        if (preg_match('~^/cars/([a-z0-9-]+)/$~', $href, $m) && $text !== '') $brands[$m[1]] ??= $text;
    }
    return $brands;
}

/** Модели марки: [slug => название] из ссылок /cars/{марка}-…/. */
function mdvParseModels(string $html, string $brandSlug, array $allBrands): array
{
    $models = [];
    foreach (mdvLinks($html) as [$href, $text]) {
        if (!preg_match('~^/cars/(' . preg_quote($brandSlug, '~') . '-[a-z0-9-]+)/$~', $href, $m)) continue;
        // «land-rover» — это марка, а не модель марки «land»
        if (isset($allBrands[$m[1]]) || $text === '') continue;
        $models[$m[1]] ??= preg_replace('~^(Дворники|Щетки|Щётки)\s+(на|для)\s+~ui', '', $text);
    }
    return $models;
}

/** Поколения модели: [id => текст ссылки]. */
function mdvParseGenerations(string $html, string $modelSlug): array
{
    $gens = [];
    foreach (mdvLinks($html) as [$href, $text]) {
        if (preg_match('~^/cars/' . preg_quote($modelSlug, '~') . '/(\d+)/$~', $href, $m)) $gens[(int)$m[1]] ??= $text;
    }
    return $gens;
}

/** Страница поколения: название, годы, размеры, крепление, артикулы Bosch. */
function mdvParseGeneration(string $html): array
{
    $h1 = preg_match('~<h1\b[^>]*>(.*?)</h1>~is', $html, $m) ? mdvText($m[1]) : '';
    $text = mdvText($html);

    // Варианты «водительский N мм + пассажирский M мм»; одиночная щётка — только водительская.
    preg_match_all('~водительск\w*\s+(\d{3,4})\s*мм(?:\s*\+\s*пассажирск\w*\s+(\d{3,4})\s*мм)?~iu', $text, $sm, PREG_SET_ORDER);
    $variants = [];
    foreach ($sm as $s) {
        $variants[$s[1] . (empty($s[2]) ? '' : '/' . $s[2])] = [(int)$s[1], (int)($s[2] ?? 0)];
    }
    // «водительский 650 мм» отдельной строкой рядом с парой 650/400 — не отдельный вариант
    foreach ($variants as $key => [$d, $p]) {
        if (!$p && array_filter($variants, fn($v) => $v[0] === $d && $v[1])) unset($variants[$key]);
    }
    $first = reset($variants) ?: [0, 0];

    // Крепление — из блока автомобиля: <span class="vi-sr vi-fit__sr">Тип крепления: Крючок 9x3</span>;
    // запасной вариант — первый текст после подписи (в таблицах товаров там бывает не крепление машины).
    $mount = preg_match('~vi-fit__sr[^>]*>\s*Тип\s+крепления\s*:?\s*([^<]+)~iu', $html, $mm)
        || preg_match('~Тип\s+крепления\s*:?\s*(?:<[^>]+>\s*)*([^<]+)~iu', $html, $mm)
        ? mb_substr(trim(html_entity_decode($mm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), " :;,\t\n\r"), 0, 100) : '';

    $articles = [];
    foreach (mdvLinks($html) as [$href]) {
        if (!preg_match('~^/catalog/([^/]+)/$~', $href, $m)) continue;
        $slug = $m[1];
        if (!preg_match('~-(3397\d{6})$~', $slug, $am)) continue;
        $articles[$am[1]] = [
            'article'  => $am[1],
            // задние Bosch: H306, H840 и Aerotwin Rear A281H, A332H
            'position' => preg_match('~(^|-)(rear|h\d{3}|a\d{3}h)(-|$)~i', $slug) ? 'rear' : 'front',
            'slug'     => $slug,
        ];
    }

    return [
        'h1'        => $h1,
        'years'     => mdvYears($h1),
        'driver'    => $first[0],
        'passenger' => $first[1],
        'variants'  => array_keys($variants),
        'mount'     => $mount,
        'isHook'    => (bool)preg_match('~крюч|hook~iu', $mount),
        'articles'  => array_values($articles),
    ];
}

// ───────────── HL-блоки ─────────────

/** DataManager HL-блока; создаёт блок и недостающие поля. */
function mdvHlEntity(string $name): string
{
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    [$table, $fields] = MDV_HL_BLOCKS[$name];

    $hl = \Bitrix\Highloadblock\HighloadBlockTable::getRow(['filter' => ['=NAME' => $name]]);
    if (!$hl) {
        $res = \Bitrix\Highloadblock\HighloadBlockTable::add(['NAME' => $name, 'TABLE_NAME' => $table]);
        if (!$res->isSuccess()) throw new RuntimeException("HL $name: " . implode(', ', $res->getErrorMessages()));
        $hl = \Bitrix\Highloadblock\HighloadBlockTable::getById($res->getId())->fetch();
        echo "Создан HL-блок $name (ID {$hl['ID']})" . PHP_EOL;
    }
    $ute = new CUserTypeEntity();
    foreach ($fields as [$code, $label, $type]) {
        if ($ute->GetList([], ['ENTITY_ID' => 'HLBLOCK_' . $hl['ID'], 'FIELD_NAME' => $code])->Fetch()) continue;
        $ute->Add([
            'ENTITY_ID' => 'HLBLOCK_' . $hl['ID'], 'FIELD_NAME' => $code, 'USER_TYPE_ID' => $type,
            'XML_ID' => $code, 'SORT' => 100, 'MULTIPLE' => 'N', 'MANDATORY' => 'N',
            'SHOW_FILTER' => 'I', 'SHOW_IN_LIST' => 'Y', 'EDIT_IN_LIST' => 'Y', 'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => ['ru' => $label], 'LIST_COLUMN_LABEL' => ['ru' => $label], 'LIST_FILTER_LABEL' => ['ru' => $label],
        ]) or print("  поле $code: {$ute->LAST_ERROR}" . PHP_EOL);
    }
    return $cache[$name] = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hl)->getDataClass();
}

function mdvSave(array $car, array $articles): void
{
    $cars = mdvHlEntity('WiperCars');
    $arts = mdvHlEntity('WiperArticles');

    $row = $cars::getList(['filter' => ['=UF_GEN_ID' => $car['UF_GEN_ID']], 'select' => ['ID']])->fetch();
    $res = $row ? $cars::update($row['ID'], $car) : $cars::add($car);
    if (!$res->isSuccess()) echo '  ошибка записи: ' . implode(', ', $res->getErrorMessages()) . PHP_EOL;

    $rs = $arts::getList(['filter' => ['=UF_GEN_ID' => $car['UF_GEN_ID']], 'select' => ['ID']]);
    while ($a = $rs->fetch()) $arts::delete($a['ID']);
    foreach ($articles as $a) {
        $arts::add(['UF_GEN_ID' => $car['UF_GEN_ID'], 'UF_ARTICLE' => $a['article'], 'UF_POSITION' => $a['position'], 'UF_SLUG' => $a['slug']]);
    }
}

// ───────────── обход ─────────────

$html = mdvFetch('/cars/', $opts, $cacheDir);
if ($html === null) {
    echo 'Не удалось загрузить /cars/ — сайт недоступен с этого IP? Попробуйте --proxy=…' . PHP_EOL;
    exit(1);
}
$brands = mdvParseBrands($html);
echo 'Марок: ' . count($brands) . PHP_EOL;

$onlyBrand = isset($opts['brand']) && $opts['brand'] !== true ? $opts['brand'] : null;
$onlyModel = isset($opts['model']) && $opts['model'] !== true ? $opts['model'] : null;
if ($onlyModel && !$onlyBrand) {
    foreach (array_keys($brands) as $b) {
        if (str_starts_with($onlyModel, $b . '-') && strlen($b) > strlen((string)$onlyBrand)) $onlyBrand = $b;
    }
}
if ($onlyBrand && !isset($brands[$onlyBrand])) {
    echo "Марки «{$onlyBrand}» нет в списке /cars/" . PHP_EOL;
    exit(1);
}

$stats = ['models' => 0, 'gens' => 0, 'noSizes' => 0, 'hook' => 0, 'bosch' => 0];
foreach ($brands as $brandSlug => $brandName) {
    if ($onlyBrand && $brandSlug !== $onlyBrand) continue;
    $html = mdvFetch("/cars/$brandSlug/", $opts, $cacheDir);
    if ($html === null) continue;
    $models = mdvParseModels($html, $brandSlug, $brands);
    echo PHP_EOL . "== $brandName ($brandSlug): моделей " . count($models) . PHP_EOL;

    foreach ($models as $modelSlug => $modelName) {
        if ($onlyModel && $modelSlug !== $onlyModel) continue;
        $html = mdvFetch("/cars/$modelSlug/", $opts, $cacheDir);
        if ($html === null) continue;
        $stats['models']++;
        $gens = mdvParseGenerations($html, $modelSlug);
        echo "-- $modelName ($modelSlug): поколений " . count($gens) . PHP_EOL;

        foreach ($gens as $genId => $genLabel) {
            $html = mdvFetch("/cars/$modelSlug/$genId/", $opts, $cacheDir);
            if ($html === null) continue;
            $g = mdvParseGeneration($html);
            $stats['gens']++;
            if (!$g['driver']) $stats['noSizes']++;
            if ($g['isHook']) $stats['hook']++;
            if ($g['articles']) $stats['bosch']++;

            [$yFrom, $yTo] = $g['years'][0] ? $g['years'] : mdvYears($genLabel);
            $genName = trim(preg_replace('~\s*\d{2}\.\d{2,4}.*$~u', '', $genLabel)) ?: $genLabel;
            $rear = array_column(array_filter($g['articles'], fn($a) => $a['position'] === 'rear'), 'article');
            $front = array_column(array_filter($g['articles'], fn($a) => $a['position'] === 'front'), 'article');
            printf("   %d %s %s–%s | %s | %s%s | Bosch: %s%s\n",
                $genId, $genName, $yFrom ?: '?', $yTo ?: '…',
                $g['variants'] ? implode(', ', $g['variants']) : 'РАЗМЕРЫ НЕ НАЙДЕНЫ',
                $g['mount'] ?: 'крепление не найдено', $g['isHook'] ? ' [крючок]' : '',
                $front ? implode(' ', $front) : '—', $rear ? ' | задние: ' . implode(' ', $rear) : '');

            if ($apply) {
                mdvSave([
                    'UF_GEN_ID' => $genId, 'UF_BRAND' => $brandName, 'UF_BRAND_CODE' => $brandSlug,
                    'UF_MODEL' => $modelName, 'UF_MODEL_CODE' => $modelSlug, 'UF_GEN_NAME' => $genName,
                    'UF_YEAR_FROM' => $yFrom, 'UF_YEAR_TO' => $yTo,
                    'UF_DRIVER_MM' => $g['driver'], 'UF_PASSENGER_MM' => $g['passenger'],
                    'UF_MOUNT' => $g['mount'], 'UF_IS_HOOK' => $g['isHook'] ? 1 : 0,
                    'UF_SIZES_RAW' => implode(', ', $g['variants']), 'UF_UPDATED' => date('Y-m-d H:i:s'),
                ], $g['articles']);
            }
        }
    }
}

printf("\nИтого: моделей %d, поколений %d, без размеров %d, крючок %d, с артикулами Bosch %d%s\n",
    $stats['models'], $stats['gens'], $stats['noSizes'], $stats['hook'], $stats['bosch'],
    $apply ? '' : ' (пробный запуск — в базу ничего не записано, для записи --apply)');
