<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

// Подключаем необходимые модули
Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Настройки
$iblockId = 42; // ID инфоблока с товарами
$imageDir = $_SERVER['DOCUMENT_ROOT'] . '/ASAM'; // Путь к папке с изображениями

// Получаем все элементы инфоблока
$filter = ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'];
$select = ['ID', 'NAME']; // Выбираем ID и название
$elements = ElementTable::getList([
    'filter' => $filter,
    'select' => $select,
])->fetchAll();

foreach ($elements as $element) {
    $elementId = $element['ID']; // ID товара

    // Получаем значение свойства CML2_ARTICLE (артикул)
    $articleCode = '';
    $property = \CIBlockElement::GetProperty(
        $iblockId,
        $elementId,
        [],
        ['CODE' => 'CML2_ARTICLE']
    )->Fetch();
    if ($property && isset($property['VALUE'])) {
        $articleCode = trim($property['VALUE']); // Удаляем лишние пробелы
    }

    if (!$articleCode) {
        echo "Артикул не найден для товара ID {$elementId}. Пропускаем.<br>";
        continue;
    }

    // Проверяем разные расширения файлов
    $extensions = ['.jpg', '.jpeg', '.png'];
    $foundFile = false;

    foreach ($extensions as $ext) {
        $imageFileName = rtrim($imageDir, '/') . '/' . $articleCode . $ext;
        if (file_exists($imageFileName)) {
            $foundFile = true;
            break;
        }
    }

    if (!$foundFile) {
        echo "Файл не найден для товара ID {$elementId} (артикул: {$articleCode})<br>";
        continue;
    }

    // Загружаем файл в медиабиблиотеку
    $fileArray = \CFile::MakeFileArray($imageFileName);
    if ($fileArray) {
        // Привязываем изображение к товару
        $fields = [
            'DETAIL_PICTURE' => $fileArray, // Главное изображение
        ];

        // Обновляем элемент инфоблока с использованием старого API
        $element = new \CIBlockElement();
        $result = $element->Update($elementId, $fields);
        if ($result) {
            echo "Изображение успешно добавлено для товара ID {$elementId} (артикул: {$articleCode})<br>";
        } else {
            echo "Ошибка при обновлении товара ID {$elementId}: " . $element->LAST_ERROR . "<br>";
        }
    } else {
        echo "Ошибка при загрузке файла {$imageFileName}<br>";
    }
}

echo "Обработка завершена.";
?>