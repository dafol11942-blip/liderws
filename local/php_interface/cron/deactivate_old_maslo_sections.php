<?php
/**
 * Разовый скрипт: отключение старых разделов верхнего уровня в ветке масел
 * (инфоблок 57). Их создала прежняя выгрузка 25.09.2026 — «Жидкость ГУР»,
 * «Масло моторное» и т.д. (#2694–#2699); новая выгрузка 1С кладёт товары в
 * дерево «Товарный запас / ВАЗ|Иномарки / Масла и технические жидкости …».
 * Пока старые разделы активны, они висят в каталоге рядом с «Товарным
 * запасом», а меню вело в них (в «Масле моторном» 34 товара вместо ~200).
 *
 * Запуск (по умолчанию — пробный, ничего не пишет):
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/deactivate_old_maslo_sections.php
 * Применить:
 *   ... deactivate_old_maslo_sections.php --apply
 *
 * Что делает --apply: ставит этим разделам ACTIVE=N. Товары не трогает —
 * пробный запуск показывает по каждому товару, есть ли такой же (по
 * артикулу) в новом дереве любой ветки и когда товар последний раз
 * обновлялся: по этому видно, живые ли это позиции или остатки прежней
 * выгрузки. Старые адреса разделов после отключения уводятся 301-м на
 * раздел с тем же названием (catalog/index.php, $redirectOrNotFound).
 */

const MASLO_IBLOCK_ID = 57;
const OLD_SECTION_IDS = [2694, 2695, 2696, 2697, 2698, 2699];

$docRoot = realpath(__DIR__ . '/../../..');
$apply = in_array('--apply', $argv ?? [], true);

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

echo ($apply ? '=== ПРИМЕНЕНИЕ ===' : '=== ПРОБНЫЙ ЗАПУСК (без --apply ничего не пишется) ===') . PHP_EOL;

/** Артикул товара: CML2_ARTICLE или свойство с названием «Артикул». */
function oldSectionArticle(int $iblockId, int $elementId): string
{
    $rs = CIBlockElement::GetProperty($iblockId, $elementId, ['sort' => 'asc'], ['EMPTY' => 'N']);
    while ($prop = $rs->Fetch()) {
        if ($prop['CODE'] === 'CML2_ARTICLE' || mb_strtolower(trim((string)$prop['NAME'])) === 'артикул') {
            $value = trim((string)$prop['VALUE']);
            if ($value !== '') return $value;
        }
    }
    return '';
}

/** Такой же товар в других местах каталога (кроме самого товара). */
function oldSectionTwins(int $elementId, string $article, string $name): array
{
    $twins = [];
    foreach (CATALOG_BRANCHES as $slug => $branch) {
        // Фильтр по несуществующему свойству Битрикс может молча отбросить
        // и вернуть любые товары — проверяем, что CML2_ARTICLE в инфоблоке есть.
        static $hasArticleProp = [];
        $hasArticleProp[$branch['id']] ??= (bool)CIBlockProperty::GetList([], ['IBLOCK_ID' => $branch['id'], 'CODE' => 'CML2_ARTICLE'])->Fetch();
        $filters = [];
        if ($article !== '' && $hasArticleProp[$branch['id']]) $filters[] = ['=PROPERTY_CML2_ARTICLE' => $article];
        $filters[] = ['=NAME' => $name];
        foreach ($filters as $f) {
            $rs = CIBlockElement::GetList([], ['IBLOCK_ID' => $branch['id'], 'ACTIVE' => 'Y', '!ID' => $elementId] + $f,
                false, ['nTopCount' => 3], ['ID', 'IBLOCK_SECTION_ID']);
            while ($row = $rs->Fetch()) {
                $twins[$row['ID']] = $slug . ' #' . $row['ID'] . ' (раздел #' . $row['IBLOCK_SECTION_ID'] . ')';
            }
            if ($twins) break;
        }
    }
    return array_values($twins);
}

$section = new CIBlockSection();
$total = 0;
$withTwin = 0;
foreach (OLD_SECTION_IDS as $sectionId) {
    $s = CIBlockSection::GetList([], ['IBLOCK_ID' => MASLO_IBLOCK_ID, 'ID' => $sectionId], false, ['ID', 'NAME', 'ACTIVE', 'IBLOCK_SECTION_ID'])->Fetch();
    if (!$s) {
        echo PHP_EOL . "#$sectionId — не найден, пропуск" . PHP_EOL;
        continue;
    }
    if ((int)$s['IBLOCK_SECTION_ID'] !== 0) {
        echo PHP_EOL . "#$sectionId «{$s['NAME']}» — не на верхнем уровне, пропуск (проверьте ID)" . PHP_EOL;
        continue;
    }
    echo PHP_EOL . "--- #$sectionId «{$s['NAME']}» (active={$s['ACTIVE']}) ---" . PHP_EOL;

    $rs = CIBlockElement::GetList(['NAME' => 'ASC'],
        ['IBLOCK_ID' => MASLO_IBLOCK_ID, 'SECTION_ID' => $sectionId, 'INCLUDE_SUBSECTIONS' => 'Y'],
        false, false, ['ID', 'NAME', 'ACTIVE', 'TIMESTAMP_X', 'CATALOG_QUANTITY']);
    while ($el = $rs->Fetch()) {
        $total++;
        $article = oldSectionArticle(MASLO_IBLOCK_ID, (int)$el['ID']);
        $twins = oldSectionTwins((int)$el['ID'], $article, (string)$el['NAME']);
        if ($twins) $withTwin++;
        printf("  #%d %s | арт. %s | остаток %s | обновлён %s | active=%s | %s\n",
            $el['ID'], $el['NAME'], $article ?: '—', (string)$el['CATALOG_QUANTITY'], $el['TIMESTAMP_X'], $el['ACTIVE'],
            $twins ? 'есть такой же: ' . implode(', ', $twins) : 'ДУБЛЯ НЕТ');
    }

    if ($apply && $s['ACTIVE'] === 'Y') {
        if ($section->Update($sectionId, ['ACTIVE' => 'N'], false, false)) {
            echo "  → раздел отключён" . PHP_EOL;
        } else {
            echo '  → ОШИБКА: ' . $section->LAST_ERROR . PHP_EOL;
        }
    }
}

echo PHP_EOL . "Итого товаров в старых разделах: $total, из них есть такой же в новом дереве: $withTwin" . PHP_EOL;
if (!$apply) {
    echo 'Разделы не тронуты. Для отключения запустите с --apply.' . PHP_EOL;
}
