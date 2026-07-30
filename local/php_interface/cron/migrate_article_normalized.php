<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';

use \Search\BrandNormalizer;

$db = \Bitrix\Main\Application::getConnection();
$normalizer = new BrandNormalizer();

$batchSize = 500;
$offset = 0;

do {
    $sql = "SELECT id, article FROM b_supplier_stock 
            WHERE article_normalized IS NULL AND article != ''
            LIMIT $batchSize OFFSET $offset";
    $rows = $db->query($sql)->fetchAll();
    
    if (empty($rows)) break;
    
    foreach ($rows as $row) {
        $aNorm = $normalizer->normalizeArticle($row['article']);
        $db->query("UPDATE b_supplier_stock SET article_normalized = '" 
            . $db->getSqlHelper()->forSql($aNorm) 
            . "' WHERE id = " . intval($row['id']));
    }
    
    $offset += $batchSize;
    echo "Обработано: $offset строк\n";
    
} while (true);

echo "Миграция article_normalized завершена.\n";