<?php
/**
 * Разовый скрипт: перенос картинок разделов из старого каталога
 * (инфоблок 42 «Лидер Нефтяников», больше не пополняется из 1С) в разделы
 * новых веток CATALOG_BRANCHES (ВАЗ / Иномарки / Масла). Названия разделов
 * при переезде на новый обмен не менялись, поэтому сопоставляем по имени.
 *
 * Запуск (по умолчанию — пробный, ничего не пишет, только показывает план):
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/copy_section_pictures.php
 * Применить:
 *   /usr/bin/php .../copy_section_pictures.php --apply
 *
 * Правила:
 *  - трогаем только разделы новых веток БЕЗ своей картинки — загруженное
 *    вручную или из 1С не перезаписываем;
 *  - имена сравниваются без учёта регистра, ё/е и лишних пробелов;
 *  - если в старом каталоге несколько разделов с таким именем (например,
 *    «Комплектующие» в разных ветках), берём тот, у кого больше совпадает
 *    цепочка родителей; при равенстве с разными картинками — пропускаем
 *    и пишем в отчёт, чтобы решить вручную;
 *  - разделы без пары или без картинки в старом каталоге остаются как есть —
 *    сайт покажет для них иконку по названию (catalogSectionIcon()).
 */

const OLD_IBLOCK_ID = 42;

$docRoot = realpath(__DIR__ . '/../../..');
$apply = in_array('--apply', $argv ?? [], true);

ob_start();
try {
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    define('NO_KEEP_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);
    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
    CModule::IncludeModule('iblock');
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    echo 'Bitrix bootstrap failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

function normSectionName(string $name): string
{
    $name = mb_strtolower(trim($name));
    $name = str_replace('ё', 'е', $name);
    return preg_replace('/\s+/u', ' ', $name);
}

/**
 * Все разделы инфоблока: [id => ['NAME', 'PARENT', 'PICTURE', 'CHAIN']],
 * CHAIN — нормализованные имена предков от ближайшего к корню.
 */
function loadSections(int $iblockId): array
{
    $res = CIBlockSection::GetList(
        ['LEFT_MARGIN' => 'ASC'],
        ['IBLOCK_ID' => $iblockId],
        false,
        ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'PICTURE']
    );
    $sections = [];
    while ($row = $res->Fetch()) {
        $sections[(int)$row['ID']] = [
            'NAME'    => (string)$row['NAME'],
            'PARENT'  => (int)$row['IBLOCK_SECTION_ID'],
            'PICTURE' => (int)$row['PICTURE'],
        ];
    }
    foreach ($sections as $id => &$s) {
        $chain = [];
        $p = $s['PARENT'];
        while ($p && isset($sections[$p]) && count($chain) < 20) {
            $chain[] = normSectionName($sections[$p]['NAME']);
            $p = $sections[$p]['PARENT'];
        }
        $s['CHAIN'] = $chain;
    }
    unset($s);
    return $sections;
}

/** Сколько ближайших предков совпадает по имени подряд. */
function chainScore(array $a, array $b): int
{
    $n = 0;
    while (isset($a[$n], $b[$n]) && $a[$n] === $b[$n]) {
        $n++;
    }
    return $n;
}

echo ($apply ? '=== ПРИМЕНЕНИЕ ===' : '=== ПРОБНЫЙ ЗАПУСК (без --apply ничего не пишется) ===') . PHP_EOL;

$old = loadSections(OLD_IBLOCK_ID);
$oldByName = [];
foreach ($old as $id => $s) {
    if ($s['PICTURE'] > 0) {
        $oldByName[normSectionName($s['NAME'])][] = $id;
    }
}
echo 'Старый каталог: разделов ' . count($old) . ', с картинкой ' . array_sum(array_map('count', $oldByName)) . PHP_EOL;

$stats = ['copied' => 0, 'has_own' => 0, 'no_match' => 0, 'ambiguous' => 0, 'error' => 0];
$section = new CIBlockSection();

foreach (CATALOG_BRANCHES as $slug => $branch) {
    $new = loadSections((int)$branch['id']);
    echo PHP_EOL . '--- ' . $branch['name'] . ' (инфоблок ' . $branch['id'] . ', разделов ' . count($new) . ') ---' . PHP_EOL;

    foreach ($new as $id => $s) {
        if ($s['PICTURE'] > 0) {
            $stats['has_own']++;
            continue;
        }
        $candidates = $oldByName[normSectionName($s['NAME'])] ?? [];
        if (!$candidates) {
            $stats['no_match']++;
            echo "  [нет пары]   #$id {$s['NAME']}" . PHP_EOL;
            continue;
        }

        // Лучший кандидат по совпадению цепочки родителей.
        $best = [];
        $bestScore = -1;
        foreach ($candidates as $oldId) {
            $score = chainScore($s['CHAIN'], $old[$oldId]['CHAIN']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$oldId];
            } elseif ($score === $bestScore) {
                $best[] = $oldId;
            }
        }
        $pictures = array_unique(array_map(fn($oldId) => $old[$oldId]['PICTURE'], $best));
        if (count($pictures) > 1) {
            $stats['ambiguous']++;
            echo "  [неоднозн.]  #$id {$s['NAME']} — подходят старые разделы #" . implode(', #', $best) . PHP_EOL;
            continue;
        }

        $oldId = $best[0];
        $path = CFile::GetPath($old[$oldId]['PICTURE']);
        echo "  [копия]      #$id {$s['NAME']} ← старый #$oldId ($path)" . PHP_EOL;
        if (!$apply) {
            $stats['copied']++;
            continue;
        }

        $file = CFile::MakeFileArray($old[$oldId]['PICTURE']);
        if (!$file || empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            $stats['error']++;
            echo "    ОШИБКА: файл картинки старого раздела не найден на диске" . PHP_EOL;
            continue;
        }
        $file['MODULE_ID'] = 'iblock';
        if ($section->Update($id, ['PICTURE' => $file], false, false)) {
            $stats['copied']++;
        } else {
            $stats['error']++;
            echo '    ОШИБКА: ' . $section->LAST_ERROR . PHP_EOL;
        }
    }
}

echo PHP_EOL . 'Итого: ' . ($apply ? 'скопировано ' : 'будет скопировано ') . $stats['copied']
    . ', уже с картинкой ' . $stats['has_own']
    . ', без пары (останется иконка) ' . $stats['no_match']
    . ', неоднозначных ' . $stats['ambiguous']
    . ', ошибок ' . $stats['error'] . PHP_EOL;
