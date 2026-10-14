<?php
/**
 * Рекомендуемые параметры АКБ для модификации автомобиля из базы ТО
 * (HL-блоки AutoBrands / AutoModels / AutoModifications — те же, что у
 * «Запчастей для ТО»). Готовой базы применяемости АКБ нет, поэтому параметры
 * считаются правилами:
 *  - полярность — по происхождению марки (отечественные и GM-Daewoo — прямая,
 *    остальные — обратная);
 *  - типоразмер корпуса и ёмкость — по объёму двигателя и топливу; дизель,
 *    премиальная марка, дизельный фургон — корпус на шаг больше; японские
 *    марки и корейские до 2008 г. — азиатские корпуса (JIS);
 *  - для популярных моделей типоразмер и полярность заданы явно (AKB_FIT_MODELS).
 * Результат — рекомендация: на сайте выводится предупреждение сверить
 * параметры со старым аккумулятором.
 */

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

// Типоразмер => [длина, ширина, высота, ёмкость от, ёмкость до, тип корпуса]
const AKB_FIT_SIZES = [
    'L1'  => [207, 175, 190, 50, 55, 'european'],
    'L2'  => [242, 175, 190, 55, 65, 'european'],
    'L3'  => [278, 175, 190, 70, 77, 'european'],
    'L4'  => [315, 175, 190, 80, 85, 'european'],
    'L5'  => [353, 175, 190, 90, 100, 'european'],
    'L6'  => [393, 175, 190, 105, 110, 'european'],
    'LB2' => [242, 175, 175, 55, 62, 'european'],
    'LB3' => [278, 175, 175, 65, 72, 'european'],
    'B19' => [187, 127, 227, 35, 45, 'asian'],
    'B24' => [238, 129, 227, 40, 50, 'asian'],
    'D23' => [232, 173, 225, 60, 70, 'asian'],
    'D26' => [260, 173, 225, 70, 80, 'asian'],
    'D31' => [306, 173, 225, 90, 100, 'asian'],
];
const AKB_FIT_EN_ORDER  = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6'];
const AKB_FIT_JIS_ORDER = ['B19', 'B24', 'D23', 'D26', 'D31'];

const AKB_FIT_BRANDS_DIRECT = ['LADA', 'GAZ', 'UAZ', 'ZAZ', 'TAGAZ', 'DAEWOO'];
const AKB_FIT_BRANDS_JP = ['TOYOTA', 'LEXUS', 'NISSAN', 'INFINITI', 'HONDA', 'ACURA', 'MAZDA', 'MITSUBISHI',
    'SUBARU', 'SUZUKI', 'DAIHATSU', 'ISUZU', 'DATSUN'];
const AKB_FIT_BRANDS_KR = ['KIA', 'HYUNDAI', 'GENESIS', 'SSANGYONG', 'RAVON'];
const AKB_FIT_BRANDS_PREMIUM = ['BMW', 'MERCEDES-BENZ', 'AUDI', 'VOLVO', 'LAND ROVER', 'JAGUAR', 'PORSCHE',
    'LEXUS', 'INFINITI', 'GENESIS', 'BENTLEY', 'MASERATI', 'CADILLAC', 'LINCOLN'];
const AKB_FIT_VAN_PATTERN = '/фургон|автобус|платформ|TRANSPORTER|SPRINTER|CRAFTER|MASTER|DUCATO|BOXER|JUMPER|TRANSIT|VITO|MOVANO|H-1|H350|PORTER|BONGO/iu';

// Популярные модели: [марка, regex модели, типоразмер (null — по правилам), полярность]. Первое совпадение.
const AKB_FIT_MODELS = [
    ['LADA', '/LARGUS/iu', 'L3', 'reverse'],
    ['LADA', '/XRAY|VESTA/iu', 'L2', 'direct'],
    ['LADA', '/./u', 'L2', 'direct'],
    ['RENAULT', '/LOGAN|SANDERO/iu', 'LB2', 'reverse'],
    ['RENAULT', '/DUSTER|KAPTUR|ARKANA|DOKKER|LODGY/iu', 'LB3', 'reverse'],
    ['KIA', '/^RIO (III|IV)|STONIC|^K5/iu', 'L2', 'reverse'],
    ['HYUNDAI', '/SOLARIS|CRETA/iu', 'L2', 'reverse'],
    ['VW', '/POLO (V|VI)|POLO седан/iu', 'L2', 'reverse'],
    ['SKODA', '/RAPID|FABIA/iu', 'L2', 'reverse'],
    ['CHEVROLET', '/NIVA/iu', 'L2', 'direct'],
    ['CHEVROLET', '/LANOS|LACETTI|AVEO|SPARK|NEXIA|COBALT|CRUZE|CAPTIVA|EPICA|EVANDA/iu', null, 'direct'],
    ['DAEWOO', '/NEXIA|ESPERO/iu', 'L2', 'reverse'],
    ['DAEWOO', '/MATIZ|TICO|DAMAS/iu', 'B19', 'direct'],
    ['GAZ', '/GAZELLE|SOBOL|VALDAI/iu', 'L3', 'direct'],
    ['UAZ', '/./u', 'L3', 'direct'],
];

function akbFitHlRow(string $hlName, array $filter): ?array
{
    static $classes = [];
    if (!array_key_exists($hlName, $classes)) {
        Loader::includeModule('highloadblock');
        $hl = HighloadBlockTable::getRow(['filter' => ['=NAME' => $hlName]]);
        $classes[$hlName] = $hl ? HighloadBlockTable::compileEntity($hl)->getDataClass() : null;
    }
    if (!$classes[$hlName]) return null;
    return $classes[$hlName]::getList(['filter' => $filter, 'limit' => 1])->fetch() ?: null;
}

function akbFitSizeStep(array $order, string $size, int $step): string
{
    $i = array_search($size, $order, true);
    return $order[max(0, min(count($order) - 1, $i + $step))];
}

/**
 * Рекомендация для модификации: машина, типоразмер, габариты, ёмкость,
 * полярность, тип корпуса, нужен ли AGM/EFB и источник («модель» — из списка
 * популярных моделей, «оценка» — по общим правилам). null — модификация не найдена.
 */
function akbFitmentForModification(int $modificationId): ?array
{
    if ($modificationId <= 0) return null;
    $mod = akbFitHlRow('AutoModifications', ['=UF_MODIFICATION_ID' => $modificationId]);
    if (!$mod) return null;
    $model = akbFitHlRow('AutoModels', ['=UF_MODEL_ID' => (int)$mod['UF_MODEL_ID']]);
    if (!$model) return null;
    $brandRow = akbFitHlRow('AutoBrands', ['=UF_BRAND_ID' => (int)$model['UF_BRAND_ID']]);
    $brand = mb_strtoupper(trim((string)($brandRow['UF_NAME'] ?? '')));
    $modelName = (string)$model['UF_NAME'];

    $diesel = mb_stripos((string)$mod['UF_FUEL'], 'дизел') !== false;
    $engine = (float)$mod['UF_ENGINE_CAPACITY'];
    $start = $mod['UF_START_DATE'] ?? null;
    $year = $start instanceof \Bitrix\Main\Type\Date ? (int)$start->format('Y') : (int)($model['UF_YEAR_FROM'] ?: 2000);
    $van = (bool)preg_match(AKB_FIT_VAN_PATTERN, $modelName);
    $premium = in_array($brand, AKB_FIT_BRANDS_PREMIUM, true);

    $size = null;
    $polarity = null;
    $source = 'оценка';
    foreach (AKB_FIT_MODELS as [$b, $rx, $sz, $pol]) {
        if ($b === $brand && preg_match($rx, $modelName)) {
            [$size, $polarity, $source] = [$sz, $pol, 'модель'];
            break;
        }
    }
    $polarity ??= in_array($brand, AKB_FIT_BRANDS_DIRECT, true) ? 'direct' : 'reverse';

    if ($size === null) {
        $asia = !$van && (in_array($brand, AKB_FIT_BRANDS_JP, true) || (in_array($brand, AKB_FIT_BRANDS_KR, true) && $year < 2008));
        if ($asia) {
            $size = $engine > 0 && $engine <= 1.3 ? 'B24' : ($engine <= 2.0 ? 'D23' : ($engine <= 3.0 ? 'D26' : 'D31'));
            if ($diesel) $size = akbFitSizeStep(AKB_FIT_JIS_ORDER, $size, 1);
        } else {
            if ($diesel) {
                $size = $engine <= 2.0 ? 'L3' : ($engine <= 3.0 ? 'L4' : 'L5');
            } else {
                $size = $engine > 0 && $engine <= 1.2 ? 'L1' : ($engine <= 1.8 ? 'L2' : ($engine <= 2.5 ? 'L3' : ($engine <= 3.5 ? 'L4' : 'L5')));
            }
            if ($premium) $size = akbFitSizeStep(AKB_FIT_EN_ORDER, $size, 1);
            if ($van && $diesel) $size = akbFitSizeStep(AKB_FIT_EN_ORDER, $size, 1);
        }
    } elseif ($diesel && in_array($size, AKB_FIT_EN_ORDER, true)) {
        $size = akbFitSizeStep(AKB_FIT_EN_ORDER, $size, 1);
    }

    [$length, $width, $height, $capFrom, $capTo, $case] = AKB_FIT_SIZES[$size];
    return [
        'CAR'      => trim(($brandRow['UF_NAME'] ?? '') . ' ' . $modelName . ', ' . $mod['UF_FULL_NAME']),
        'BRAND_ID' => (int)$model['UF_BRAND_ID'],
        'MODEL_ID' => (int)$mod['UF_MODEL_ID'],
        'MOD_ID'   => $modificationId,
        'SIZE'     => $size,
        'LENGTH'   => $length,
        'WIDTH'    => $width,
        'HEIGHT'   => $height,
        'CAP_FROM' => $capFrom,
        'CAP_TO'   => $capTo,
        'POLARITY' => $polarity,
        'CASE'     => $case,
        'AGM'      => $premium && $year >= 2012,
        'SOURCE'   => $source,
    ];
}
