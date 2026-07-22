<?php
/**
 * Массовое создание HL-блоков для каталога ТО
 * Запуск: php local/php_interface/cron/create_hl_blocks.php
 */

$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');
Loader::includeModule('main');

// ========= КОНФИГУРАЦИЯ ВСЕХ 6 БЛОКОВ =========
$blocks = [
    // 1. МАРКИ
    [
        'NAME'       => 'AutoBrands',
        'TABLE_NAME' => 'b_hlbd_auto_brands',
        'FIELDS'     => [
            ['UF_BRAND_ID', 'ID марки',        'integer'],
            ['UF_NAME',     'Название',         'string'],
            ['UF_CODE',     'Символьный код',   'string'],
        ],
    ],
    // 2. МОДЕЛИ
    [
        'NAME'       => 'AutoModels',
        'TABLE_NAME' => 'b_hlbd_auto_models',
        'FIELDS'     => [
            ['UF_MODEL_ID',  'ID модели',       'integer'],
            ['UF_BRAND_ID',  'ID марки',        'integer'],
            ['UF_NAME',      'Название модели',  'string'],
            ['UF_YEAR_FROM', 'Год от',          'integer'],
            ['UF_YEAR_TO',   'Год до',          'integer'],
        ],
    ],
    // 3. МОДИФИКАЦИИ
    [
        'NAME'       => 'AutoModifications',
        'TABLE_NAME' => 'b_hlbd_auto_modifications',
        'FIELDS'     => [
            ['UF_MODIFICATION_ID',     'ID модификации',     'integer'],
            ['UF_MODEL_ID',            'ID модели',          'integer'],
            ['UF_FULL_NAME',           'Полное название',     'string'],
            ['UF_ENGINE_CODE',         'Код двигателя',       'string'],
            ['UF_CONSTRUCTION_TYPE',   'Тип кузова',          'string'],
            ['UF_FUEL',                'Топливо',             'string'],
            ['UF_HORSE_POWER',         'Мощность л.с.',       'integer'],
            ['UF_ENGINE_CAPACITY',     'Объём двигателя',     'double'],
            ['UF_NUMBER_OF_CYLINDERS', 'Цилиндров',           'integer'],
            ['UF_VALVES',              'Клапанов на цилиндр', 'integer'],
            ['UF_VALVES_TOTAL',        'Всего клапанов',      'integer'],
            ['UF_MOTOR_TYPE',          'Тип двигателя',       'string'],
            ['UF_START_DATE',          'Дата начала',         'date'],
            ['UF_END_DATE',            'Дата окончания',      'date'],
        ],
    ],
    // 4. ЗАПЧАСТИ
    [
        'NAME'       => 'AutoParts',
        'TABLE_NAME' => 'b_hlbd_auto_parts',
        'FIELDS'     => [
            ['UF_MODIFICATION_ID',   'ID модификации',   'integer'],
            ['UF_ITEM_NAME',         'Название запчасти', 'string'],
            ['UF_PART_NUMBER',       'Артикул',           'string'],
            ['UF_QUANTITY',          'Количество',        'integer'],
            ['UF_COMMENT',           'Комментарий',       'string'],
            ['UF_IS_NECESSARY',      'Обязательная',      'boolean'],
            ['UF_MANUFACTURER_NAME', 'Производитель',     'string'],
            ['UF_CATEGORY_ID',       'ID категории',      'integer'],
        ],
    ],
    // 5. МАСЛА
    [
        'NAME'       => 'AutoOils',
        'TABLE_NAME' => 'b_hlbd_auto_oils',
        'FIELDS'     => [
            ['UF_MODIFICATION_ID',   'ID модификации',   'integer'],
            ['UF_GROUP_NAME',        'Название продукта', 'string'],
            ['UF_TYPE_NAME',         'Тип жидкости',      'string'],
            ['UF_ART_NUMBER',        'Артикул',           'string'],
            ['UF_VOLUME',            'Объём',             'double'],
            ['UF_MANUFACTURER_NAME', 'Производитель',     'string'],
            ['UF_ORDER_POSITION',    'Позиция сортировки','integer'],
        ],
    ],
    // 6. СПЕЦИФИКАЦИИ
    [
        'NAME'       => 'AutoSpecifications',
        'TABLE_NAME' => 'b_hlbd_auto_specifications',
        'FIELDS'     => [
            ['UF_MODIFICATION_ID', 'ID модификации',  'integer'],
            ['UF_NAME',            'Название',         'string'],
            ['UF_SEO_URL',         'SEO URL',          'string'],
            ['UF_VOLUME',          'Объём заправки',   'double'],
            ['UF_COMMENT',         'Комментарий',      'string'],
            ['UF_PROPERTIES',      'Допуски/спецификации','string'],
        ],
    ],
];

$created = 0;
$exists  = 0;

// Работаем через CUserTypeEntity (старый API)
$userTypeEnt = new \CUserTypeEntity();

foreach ($blocks as $block) {
    $name      = $block['NAME'];
    $tableName = $block['TABLE_NAME'];

    // Проверяем, существует ли уже HL-блок
    $existing = HighloadBlockTable::getRow(['filter' => ['=NAME' => $name]]);
    if ($existing) {
        echo "⏭️  HL-блок '$name' уже существует (ID: {$existing['ID']})\n";
        $hlBlockId = $existing['ID'];
        $exists++;
    } else {
        // Создаём HL-блок
        $result = HighloadBlockTable::add([
            'NAME'       => $name,
            'TABLE_NAME' => $tableName,
        ]);

        if (!$result->isSuccess()) {
            echo "❌ Ошибка создания '$name': " . implode(', ', $result->getErrorMessages()) . "\n";
            continue;
        }
        $hlBlockId = $result->getId();
        echo "✅ Создан HL-блок '$name' (ID: $hlBlockId, таблица: $tableName)\n";
        $created++;
    }

    // Добавляем поля через CUserTypeEntity
    foreach ($block['FIELDS'] as $field) {
        [$fieldName, $label, $type] = $field;

        // Проверяем, существует ли уже поле
        $existingField = $userTypeEnt->GetList(
            [],
            [
                'ENTITY_ID'  => "HLBLOCK_{$hlBlockId}",
                'FIELD_NAME' => $fieldName,
            ]
        )->Fetch();

        if ($existingField) {
            echo "   ⏭️  Поле $fieldName уже есть\n";
            continue;
        }

        $fieldId = $userTypeEnt->Add([
            'ENTITY_ID'         => "HLBLOCK_{$hlBlockId}",
            'FIELD_NAME'        => $fieldName,
            'USER_TYPE_ID'      => $type,
            'XML_ID'            => $fieldName,
            'SORT'              => 100,
            'MULTIPLE'          => 'N',
            'MANDATORY'         => 'N',
            'SHOW_FILTER'       => 'N',
            'SHOW_IN_LIST'      => 'Y',
            'EDIT_IN_LIST'      => 'Y',
            'IS_SEARCHABLE'     => 'N',
            'EDIT_FORM_LABEL'   => ['ru' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label],
        ]);

        if ($fieldId) {
            echo "   ✅ Поле $fieldName ($label)\n";
        } else {
            echo "   ❌ Ошибка поля $fieldName: " . $userTypeEnt->LAST_ERROR . "\n";
        }
    }
    echo "\n";
}

echo "========================================\n";
echo "🎉 Готово! Создано: $created, уже было: $exists\n";
echo "Всего блоков: " . ($created + $exists) . " из 6\n";
echo "========================================\n";
