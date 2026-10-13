<?php
/**
 * Диагностика разделов каталога (только чтение, ничего не меняет).
 *
 * Роутер catalog/index.php ищет раздел по символьному коду последнего
 * сегмента URL. Если в ветке несколько разделов с одним CODE (например,
 * старый «Масло моторное» на верхнем уровне и новый из 1С внутри
 * «Товарный запас»), открывается первый попавшийся — и товаров в нём меньше.
 * Скрипт показывает:
 *  - разделы верхнего уровня каждой ветки;
 *  - все группы разделов с одинаковым CODE: путь, XML_ID, активность,
 *    число активных товаров (с подразделами) и сколько из них в наличии.
 *
 * Запуск:
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/diag_catalog_sections.php
 *   ... diag_catalog_sections.php maslo     — только одна ветка
 */

$docRoot = realpath(__DIR__ . '/../../..');
$onlyBranch = $argv[1] ?? '';

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
    echo 'Bitrix bootstrap failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

function diagCount(int $iblockId, int $sectionId, bool $inStock): int
{
    $filter = ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $sectionId, 'INCLUDE_SUBSECTIONS' => 'Y', 'ACTIVE' => 'Y'];
    if ($inStock) {
        $filter['>CATALOG_QUANTITY'] = 0;
    }
    return (int)CIBlockElement::GetList([], $filter, []);
}

foreach (CATALOG_BRANCHES as $slug => $branch) {
    if ($onlyBranch !== '' && $onlyBranch !== $slug) continue;
    $iblockId = (int)$branch['id'];

    $sections = [];
    $res = CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => $iblockId], false,
        ['ID', 'NAME', 'CODE', 'XML_ID', 'ACTIVE', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL', 'DATE_CREATE']);
    while ($row = $res->Fetch()) {
        $sections[(int)$row['ID']] = $row;
    }
    $path = function (int $id) use ($sections): string {
        $names = [];
        $p = (int)($sections[$id]['IBLOCK_SECTION_ID'] ?? 0);
        while ($p && isset($sections[$p])) {
            array_unshift($names, $sections[$p]['NAME']);
            $p = (int)$sections[$p]['IBLOCK_SECTION_ID'];
        }
        return $names ? implode(' / ', $names) : '(верхний уровень)';
    };
    $line = function (int $id) use ($sections, $iblockId, $path): string {
        $s = $sections[$id];
        return sprintf('#%d «%s» code=%s xml_id=%s active=%s создан=%s | в: %s | товаров: %d, в наличии: %d',
            $id, $s['NAME'], $s['CODE'] ?: '—', $s['XML_ID'] ?: '—', $s['ACTIVE'], $s['DATE_CREATE'], $path($id),
            diagCount($iblockId, $id, false), diagCount($iblockId, $id, true));
    };

    $root = resolveEffectiveRoot($iblockId);
    echo PHP_EOL . '=== ' . $branch['name'] . " (/catalog/$slug/, инфоблок $iblockId, разделов " . count($sections) . ') ===' . PHP_EOL;
    echo 'Эффективный корень: #' . $root['rootId'] . ', пропущенные обёртки: ' . ($root['skippedIds'] ? '#' . implode(', #', $root['skippedIds']) : 'нет') . PHP_EOL;
    echo 'Товаров в ветке всего: ' . (int)CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], [])
        . ', в наличии: ' . (int)CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', '>CATALOG_QUANTITY' => 0], [])
        . ', без раздела: ' . (int)CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'SECTION_ID' => false], []) . PHP_EOL;

    echo PHP_EOL . '-- Верхний уровень --' . PHP_EOL;
    foreach ($sections as $id => $s) {
        if ((int)$s['IBLOCK_SECTION_ID'] === 0) echo '  ' . $line($id) . PHP_EOL;
    }

    echo PHP_EOL . '-- Разделы с одинаковым CODE --' . PHP_EOL;
    $byCode = [];
    foreach ($sections as $id => $s) {
        if ($s['CODE'] !== '' && $s['CODE'] !== null) $byCode[$s['CODE']][] = $id;
    }
    $dups = 0;
    foreach ($byCode as $code => $ids) {
        if (count($ids) < 2) continue;
        $dups++;
        echo "  code=$code:" . PHP_EOL;
        foreach ($ids as $id) echo '    ' . $line($id) . PHP_EOL;
    }
    if (!$dups) echo '  нет' . PHP_EOL;
}
