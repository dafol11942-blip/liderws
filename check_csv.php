<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/';
$files = ['marks.csv', 'models.csv', 'modifications.csv', 'items.csv', 'oils.csv', 'specs.csv'];

echo "<h2>Проверка CSV-файлов</h2>";

foreach ($files as $f) {
    $path = $baseDir . $f;
    echo "<h3>$f</h3>";
    
    if (!file_exists($path)) {
        echo "<p style='color:red;'>❌ Файл не найден: $path</p>";
        continue;
    }
    
    echo "<p>✅ Файл найден, размер: " . filesize($path) . " байт</p>";
    
    // Читаем первые 3 строки
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    
    echo "<p>Всего строк: " . count($lines) . "</p>";
    echo "<p>Первые 200 символов:</p>";
    echo "<pre>" . htmlspecialchars(substr($content, 0, 200)) . "</pre>";
    
    // Пробуем распарсить первую строку данных
    $h = fopen($path, 'r');
    $header = fgetcsv($h, 0, ',');
    echo "<p>Заголовок (запятая): " . print_r($header, true) . "</p>";
    
    // Проверим, может быть разделитель — точка с запятой
    rewind($h);
    $header2 = fgetcsv($h, 0, ';');
    echo "<p>Заголовок (точка с запятой): " . print_r($header2, true) . "</p>";
    
    fclose($h);
}