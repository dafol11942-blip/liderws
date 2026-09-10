<?php
/**
 * Автозаполнение и унификация свойств товаров на основе наименования.
 *
 * Идея: для каждого целевого свойства собирается словарь уже известных
 * значений (для списочных свойств — все варианты из справочника свойства,
 * для строковых — уже встречающиеся у других товаров значения). Затем:
 *
 *  1) для товаров без значения свойства ищем в NAME вхождение одного из
 *     известных значений (по границам слова, без учёта регистра) и
 *     проставляем его;
 *  2) значения, которые различаются только регистром/пробелами/дефисами
 *     ("Белый" / "белый" / "БЕЛЫЙ"), схлопываются в одно каноническое —
 *     то, что реально встречается чаще остальных. Канон используется и при
 *     заполнении пустых свойств, и (если включено) при переписывании уже
 *     стоящих значений — иначе один и тот же цвет/бренд будет давать в
 *     смарт-фильтре несколько разных пунктов вместо одного.
 *
 * Новые (несуществующие в каталоге) значения никогда не придумываются —
 * это исключает порчу каталога случайным распознаванием.
 *
 * Список обрабатываемых свойств не хардкодится: берутся ВСЕ свойства
 * инфоблока типа "список" (L) и "строка" (S), кроме явно служебных
 * (см. $EXCLUDE_CODES) — F/N и не годятся для текстового поиска.
 * Это безопасно само по себе: если у свойства нет ни одного известного
 * значения (пустой справочник и ни один товар ещё не заполнен), для
 * него просто не из чего построить словарь, и скрипт его молча
 * пропускает (см. "N вариантов" в выводе) — лишние свойства в списке
 * ничего не портят, только не дают эффекта.
 *
 * По умолчанию скрипт работает в режиме DRY_RUN (только печатает, что
 * собирается изменить) и не трогает уже заполненные свойства. Проверьте
 * вывод на выборке (LIMIT) и только потом включайте DRY_RUN=false.
 *
 * Запуск: php local/scripts/autofill_product_properties.php
 */

$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');
set_time_limit(0);

// ===================== НАСТРОЙКИ =====================

// ID инфоблока каталога товаров
$IBLOCK_ID = 42;

// true — только показать, что было бы изменено, ничего не записывать
$DRY_RUN = false;

// true — по имени переподбирать значение и для уже заполненных свойств (агрессивно)
$OVERWRITE = false;

// true — переписывать уже стоящие значения на канонический вариант написания
// (без повторного распознавания по имени — просто убирает дубли-написания одного и того же значения)
$NORMALIZE_EXISTING = true;

// Ограничить число обрабатываемых товаров (0 = без ограничения), удобно для теста
$LIMIT = 200;

// Коды свойств, которые НЕ трогаем, даже если они типа L/S — служебные поля
// 1С-обмена и подобное, никак не связанное с текстом названия товара.
$EXCLUDE_CODES = [
    'CML2_ARTICLE',
    'CML2_BASE_UNIT',
    'CML2_BAR_CODE',
    'CML2_TRAITS',
    'CML2_TAXES',
    'CML2_ATTRIBUTES',
    'IN_RECOMMEND',
    'IN_STOCK',
    'NAIMENOVANIE_TOVARA_V_UCHETNOY_SISTEME_POSTAVSHCHI',
    'KOD_TOVARA_V_UCHETNOY_SISTEME_POSTAVSHCHIKA',
];

// Минимальная длина значения словаря, чтобы участвовать в поиске (отсекает шум вида "1", "-")
$MIN_VALUE_LENGTH = 2;

// Частые служебные слова (предлоги/союзы), которые никогда не считаем
// найденным значением, даже если они каким-то образом попали в словарь
// свойства (реальный случай: в справочнике "Бренд" оказалось значение "НА" —
// оно находилось в любом названии со словом "на"). Резать по длине нельзя:
// короткие настоящие бренды типа "GM" не должны пострадать.
$STOPWORDS = [
    'на', 'и', 'с', 'со', 'по', 'для', 'от', 'до', 'из', 'за', 'не', 'но', 'как', 'или', 'а', 'у', 'к', 'о', 'в', 'то',
    'the', 'for', 'and', 'or', 'in', 'on', 'of', 'to', 'a', 'an',
];

// Максимум значений, проставляемых в одно множественное свойство за раз
$MAX_MATCHES_PER_MULTIPLE_PROPERTY = 5;

// ===================== СЛУЖЕБНЫЕ ФУНКЦИИ =====================

function buildBoundaryRegex(string $needle): string
{
    $quoted = preg_quote($needle, '/');
    return '/(?<![\p{L}\p{N}])' . $quoted . '(?![\p{L}\p{N}])/iu';
}

/**
 * Ключ для группировки "одинаковых по сути" значений: регистр, дефисы и
 * пробелы не должны давать разные пункты фильтра.
 */
function normalizeKey(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = preg_replace('/\s*-\s*/u', '-', $value);
    $value = mb_strtolower($value, 'UTF-8');
    return rtrim($value, '.,;: ');
}

/**
 * true, если найденный фрагмент — единственное содержимое пары скобок,
 * т.е. вокруг него (без учёта пробелов) сразу "(" и ")": "... (LECAR) ..."
 * Отличаем такой явный "бренд в скобках" от значения, которое просто
 * оказалось частью списка через запятую внутри скобок: "(i30, SOLARIS,
 * KIA Ceed, ...)" — там перед "KIA" стоит ", ", а не "(", и такое
 * совпадение не должно считаться сильным сигналом "это и есть бренд".
 */
function isTightlyParenthesized(string $name, int $byteOffset, int $byteLen): bool
{
    $before = rtrim(substr($name, 0, $byteOffset));
    $after = ltrim(substr($name, $byteOffset + $byteLen));
    return substr($before, -1) === '(' && substr($after, 0, 1) === ')';
}

/**
 * Находит в $name все значения словаря и возвращает до $maxMatches лучших.
 * Приоритет отбора:
 *   1) значение, которое само по себе — всё содержимое пары скобок
 *      ("... (LECAR) ...") — самый надёжный сигнал "это и есть бренд/тип";
 *   2) при остальных равных — то, что встречается РАНЬШЕ в названии;
 *   3) при равенстве позиции — более длинный текст.
 *
 * Два реальных случая, из-за которых так: в "Свеча Denso ... Nissan
 * Juke/Mazda CX-5 ..." словарь бренда содержит и "Denso" (настоящий
 * производитель, стоит первым словом), и "Nissan"/"Mazda" (марки авто в
 * списке применимости дальше по тексту) — тут выигрывает более ранний.
 * А в "Концевик двери Renault (Logan/Largus) (LECAR) в сборе" всё наоборот:
 * марка авто "Renault" стоит раньше, а настоящий бренд "LECAR" — в
 * скобках в конце; тут одной только позиции недостаточно, и решают скобки.
 *
 * @param array $vocab Список ['value' => string, 'id' => int|null]
 * @return array Список подходящих элементов словаря (без повторов)
 */
function matchVocabInName(string $name, array $vocab, int $maxMatches): array
{
    $candidates = [];
    foreach ($vocab as $entry) {
        if (preg_match(buildBoundaryRegex($entry['value']), $name, $m, PREG_OFFSET_CAPTURE)) {
            $offset = $m[0][1];
            $tight = isTightlyParenthesized($name, $offset, strlen($m[0][0]));
            $candidates[] = ['entry' => $entry, 'offset' => $offset, 'len' => mb_strlen($entry['value']), 'tight' => $tight];
        }
    }
    if (empty($candidates)) {
        return [];
    }
    usort($candidates, static function ($a, $b) {
        if ($a['tight'] !== $b['tight']) {
            return $b['tight'] <=> $a['tight'];
        }
        if ($a['offset'] !== $b['offset']) {
            return $a['offset'] <=> $b['offset'];
        }
        return $b['len'] <=> $a['len'];
    });

    $found = [];
    $usedValues = [];
    foreach ($candidates as $c) {
        $value = $c['entry']['value'];
        if (isset($usedValues[$value])) {
            continue;
        }
        $found[] = $c['entry'];
        $usedValues[$value] = true;
        if (count($found) >= $maxMatches) {
            break;
        }
    }
    return $found;
}

/**
 * Группирует записи словаря по normalizeKey() и для каждой группы выбирает
 * канон — вариант с максимальной частотой использования (при равенстве —
 * самый короткий/первый). Возвращает map "id/value варианта" => "канон".
 */
function buildCanonicalMap(array $vocab, array $usageCounts, string $keyField): array
{
    $groups = [];
    foreach ($vocab as $entry) {
        $groups[normalizeKey($entry['value'])][] = $entry;
    }

    $canonicalMap = [];
    foreach ($groups as $members) {
        if (count($members) < 2) {
            $canonicalMap[$members[0][$keyField]] = $members[0];
            continue;
        }
        usort($members, static function ($a, $b) use ($usageCounts, $keyField) {
            $cntA = $usageCounts[$a[$keyField]] ?? 0;
            $cntB = $usageCounts[$b[$keyField]] ?? 0;
            if ($cntA !== $cntB) {
                return $cntB <=> $cntA;
            }
            return mb_strlen($a['value']) <=> mb_strlen($b['value']);
        });
        $canonical = $members[0];
        foreach ($members as $member) {
            $canonicalMap[$member[$keyField]] = $canonical;
        }
    }
    return $canonicalMap;
}

// ===================== СБОР ИНФОРМАЦИИ О СВОЙСТВАХ =====================

echo "========================================\n";
echo "  Автозаполнение и унификация свойств товаров по названию\n";
echo "  IBLOCK_ID=$IBLOCK_ID | DRY_RUN=" . ($DRY_RUN ? 'да' : 'НЕТ (пишем в БД)') . " | OVERWRITE=" . ($OVERWRITE ? 'да' : 'нет') . " | NORMALIZE_EXISTING=" . ($NORMALIZE_EXISTING ? 'да' : 'нет') . "\n";
echo "========================================\n\n";

$propertyInfo = [];   // code => ['ID'=>, 'PROPERTY_TYPE'=>, 'MULTIPLE'=>, 'NAME'=>]
$propertyVocab = [];  // code => [ ['value'=>..,'id'=>..], ... ] сортировка по убыв. длины

// Автообнаружение: берём все свойства инфоблока типа L/S, кроме исключённых
$PROPERTY_CODES = [];
$resAllProps = CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y']);
while ($p = $resAllProps->Fetch()) {
    $code = $p['CODE'];
    if ($code === '' || in_array($code, $EXCLUDE_CODES, true)) {
        continue;
    }
    if (!in_array($p['PROPERTY_TYPE'], ['L', 'S'], true)) {
        continue;
    }
    $PROPERTY_CODES[] = $code;
}
echo "Автообнаружено свойств для обработки: " . count($PROPERTY_CODES) . " (типы L/S, без служебных)\n\n";

foreach ($PROPERTY_CODES as $code) {
    $res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code]);
    $prop = $res->Fetch();
    if (!$prop) {
        echo "⚠️  Свойство '$code' не найдено в инфоблоке $IBLOCK_ID — пропуск\n";
        continue;
    }

    $propertyInfo[$code] = $prop;

    if ($prop['PROPERTY_TYPE'] === 'L') {
        $vocab = [];
        $enumRes = CIBlockPropertyEnum::GetList(['VALUE' => 'ASC'], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code]);
        while ($enumRow = $enumRes->Fetch()) {
            $value = trim($enumRow['VALUE']);
            if (mb_strlen($value) < $MIN_VALUE_LENGTH) {
                continue;
            }
            if (in_array(normalizeKey($value), $STOPWORDS, true)) {
                continue;
            }
            $vocab[] = ['value' => $value, 'id' => (int)$enumRow['ID']];
        }
        usort($vocab, static fn($a, $b) => mb_strlen($b['value']) <=> mb_strlen($a['value']));
        $propertyVocab[$code] = $vocab;
        echo "ℹ️  [$code] список: " . count($vocab) . " вариантов из справочника свойства\n";
    } elseif ($prop['PROPERTY_TYPE'] === 'S') {
        // Словарь для строкового свойства соберём позже, из фактических значений товаров
        $propertyVocab[$code] = [];
        echo "ℹ️  [$code] строковое свойство — словарь будет собран из уже заполненных товаров\n";
    } else {
        echo "⚠️  [$code] тип '{$prop['PROPERTY_TYPE']}' не поддерживается автозаполнением — пропуск\n";
        unset($propertyInfo[$code]);
    }
}

$targetCodes = array_keys($propertyInfo);
if (empty($targetCodes)) {
    die("\n❌ Нет ни одного поддерживаемого свойства для заполнения.\n");
}

echo "\n";

// ===================== ПРОХОД 1: ТЕКУЩИЕ ЗНАЧЕНИЯ ТОВАРОВ + ЧАСТОТЫ =====================

$elements = []; // id => ['NAME'=>, 'PROPS'=>[code => [arProp...]]]
$usageCountById = [];    // code => [enumId => count]      (для L)
$usageCountByValue = []; // code => [строка => count]      (для S)

// Статический CIBlockElement::GetProperty($IBLOCK_ID, $ID, ...) в этой сборке
// падает на внутреннем экранировании (mysqli::real_escape_string() получает
// массив вместо строки) независимо от переданных $arOrder/$arFilter — судя
// по всему, баг самого метода на этой версии ядра. Поэтому читаем товар и
// его свойства через объектный GetNextElement()->GetProperties(), это
// самый распространённый и надёжный способ в Bitrix.
$filter = ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y'];
$dbEl = CIBlockElement::GetList(['ID' => 'ASC'], $filter, false, false, ['ID', 'NAME']);
$total = 0;
while ($obEl = $dbEl->GetNextElement()) {
    $arEl = $obEl->GetFields();
    $id = $arEl['ID'];
    $elements[$id] = ['NAME' => $arEl['NAME'], 'PROPS' => []];
    $total++;

    $arProps = $obEl->GetProperties();
    foreach ($arProps as $code => $arProp) {
        if (!isset($propertyInfo[$code])) {
            continue;
        }
        $rawValues = is_array($arProp['VALUE']) ? array_values($arProp['VALUE']) : [$arProp['VALUE']];
        foreach ($rawValues as $rawValue) {
            if ($rawValue === null || $rawValue === '' || $rawValue === false) {
                continue;
            }
            $singleProp = $arProp;
            $singleProp['VALUE'] = $rawValue;
            $elements[$id]['PROPS'][$code][] = $singleProp;

            if ($propertyInfo[$code]['PROPERTY_TYPE'] === 'L') {
                $enumId = (int)$rawValue;
                $usageCountById[$code][$enumId] = ($usageCountById[$code][$enumId] ?? 0) + 1;
            } elseif ($propertyInfo[$code]['PROPERTY_TYPE'] === 'S') {
                $value = trim((string)$rawValue);
                if (mb_strlen($value) >= $MIN_VALUE_LENGTH && !in_array(normalizeKey($value), $STOPWORDS, true)) {
                    $propertyVocab[$code][$value] = ['value' => $value, 'id' => null];
                    $usageCountByValue[$code][$value] = ($usageCountByValue[$code][$value] ?? 0) + 1;
                }
            }
        }
    }

    if ($LIMIT > 0 && $total >= $LIMIT) {
        break;
    }
}
echo "Товаров для анализа: $total\n";

foreach ($propertyInfo as $code => $prop) {
    if ($prop['PROPERTY_TYPE'] === 'S') {
        $vocab = array_values($propertyVocab[$code]);
        usort($vocab, static fn($a, $b) => mb_strlen($b['value']) <=> mb_strlen($a['value']));
        $propertyVocab[$code] = $vocab;
        echo "ℹ️  [$code] словарь из факт. значений: " . count($vocab) . " уникальных\n";
    }
}

echo "\n----------------------------------------\n\n";

// ===================== КАНОНИЗАЦИЯ ЗНАЧЕНИЙ =====================
// Для каждого свойства схлопываем варианты, различающиеся только
// регистром/пробелами/дефисами, в одно наиболее часто встречающееся.

$canonicalById = [];    // code => [rawEnumId => ['value'=>canonValue,'id'=>canonId]]
$canonicalByValue = []; // code => [rawString => ['value'=>canonValue,'id'=>null]]

foreach ($targetCodes as $code) {
    $isList = $propertyInfo[$code]['PROPERTY_TYPE'] === 'L';
    $keyField = $isList ? 'id' : 'value';
    $usageCounts = $isList ? ($usageCountById[$code] ?? []) : ($usageCountByValue[$code] ?? []);

    $map = buildCanonicalMap($propertyVocab[$code], $usageCounts, $keyField);
    if ($isList) {
        $canonicalById[$code] = $map;
    } else {
        $canonicalByValue[$code] = $map;
    }

    // Отчёт по группам, где реально есть что объединять
    $groups = [];
    foreach ($propertyVocab[$code] as $entry) {
        $groups[normalizeKey($entry['value'])][] = $entry;
    }
    foreach ($groups as $members) {
        if (count($members) < 2) {
            continue;
        }
        $canonValue = $map[$members[0][$keyField]]['value'];
        $parts = [];
        foreach ($members as $m) {
            $cnt = $usageCounts[$m[$keyField]] ?? 0;
            $parts[] = "\"{$m['value']}\"($cnt)";
        }
        echo "🔀 [$code] объединяются: " . implode(' + ', $parts) . " -> канон \"$canonValue\"\n";
    }
}

echo "\n----------------------------------------\n\n";

// ===================== ПРОХОД 2: ЗАПОЛНЕНИЕ И УНИФИКАЦИЯ =====================

$stats = array_fill_keys($targetCodes, 0);
$touchedElements = 0;

foreach ($elements as $id => $el) {
    $name = $el['NAME'];
    $updates = []; // code => enum id | array of ids | string value | array of strings

    foreach ($targetCodes as $code) {
        $isList = $propertyInfo[$code]['PROPERTY_TYPE'] === 'L';
        $isMultiple = ($propertyInfo[$code]['MULTIPLE'] === 'Y');
        $canonicalMap = $isList ? $canonicalById[$code] : $canonicalByValue[$code];
        $hasValue = !empty($el['PROPS'][$code]);

        if ($hasValue && !$OVERWRITE) {
            if (!$NORMALIZE_EXISTING) {
                continue;
            }
            // Только унификация написания уже стоящих значений, без повторного распознавания по имени
            $rawValues = array_map(static fn($p) => $isList ? (int)$p['VALUE'] : trim((string)$p['VALUE']), $el['PROPS'][$code]);
            $canonEntries = [];
            foreach ($rawValues as $raw) {
                $canonEntries[] = $canonicalMap[$raw] ?? ['value' => $raw, 'id' => $raw];
            }
            $canonIds = array_values(array_unique(array_column($canonEntries, $isList ? 'id' : 'value')));
            $rawUnique = array_values(array_unique($rawValues));
            sort($canonIds);
            sort($rawUnique);
            if ($canonIds === $rawUnique) {
                continue; // уже канонично
            }

            $value = $isMultiple ? $canonIds : $canonIds[0];
            $labels = implode(', ', array_unique(array_column($canonEntries, 'value')));
            echo ($DRY_RUN ? '[DRY-NORM] ' : '[NORM] ') . "ID=$id \"$name\" :: $code => $labels\n";
            $updates[$code] = $value;
            $stats[$code]++;
            continue;
        }

        if (empty($propertyVocab[$code])) {
            continue;
        }

        $maxMatches = $isMultiple ? $MAX_MATCHES_PER_MULTIPLE_PROPERTY : 1;
        $matches = matchVocabInName($name, $propertyVocab[$code], $maxMatches);
        if (empty($matches)) {
            continue;
        }

        // Сразу приводим найденные варианты к канону
        $canonEntries = array_map(static function ($m) use ($canonicalMap, $isList) {
            $key = $isList ? $m['id'] : $m['value'];
            return $canonicalMap[$key] ?? $m;
        }, $matches);

        if ($isList) {
            $ids = array_values(array_unique(array_column($canonEntries, 'id')));
            $value = $isMultiple ? $ids : $ids[0];
        } else {
            $vals = array_values(array_unique(array_column($canonEntries, 'value')));
            $value = $isMultiple ? $vals : $vals[0];
        }

        $labels = implode(', ', array_unique(array_column($canonEntries, 'value')));
        echo ($DRY_RUN ? '[DRY] ' : '[SET] ') . "ID=$id \"$name\" :: $code => $labels\n";

        $updates[$code] = $value;
        $stats[$code]++;
    }

    if (!empty($updates)) {
        $touchedElements++;
        if (!$DRY_RUN) {
            $res = CIBlockElement::SetPropertyValuesEx($id, $IBLOCK_ID, $updates);
            if (!$res) {
                echo "❌ Ошибка обновления свойств товара ID=$id\n";
            }
        }
    }
}

echo "\n========================================\n";
echo ($DRY_RUN ? "🔎 Проверка завершена (ничего не записано)\n" : "🎉 Обновление завершено\n");
echo "Товаров с изменениями: $touchedElements из $total\n";
foreach ($stats as $code => $count) {
    if ($count > 0) {
        echo "  $code: $count\n";
    }
}
echo "========================================\n";
