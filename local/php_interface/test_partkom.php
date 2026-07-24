<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';

use Lider\Supplier\PartKomConnector;
use Lider\Search\InstantSearcher;

$pk = new PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'LidGates16']);
$items = $pk->searchByBrandArticle('MANN-FILTER', 'W7008');
echo "Total: " . count($items) . "\n";

// Посмотрим уникальность stock_id + warehouse
$seen = [];
foreach ($items as $i) {
    $key = $i->stockId . ' | ' . ($i->warehouse ?? '?');
    if (!isset($seen[$key])) $seen[$key] = 0;
    $seen[$key]++;
}
echo "\n=== stockId + warehouse уникальность ===\n";
foreach ($seen as $k => $c) {
    echo "  $k → $c шт\n";
}
echo "Уникальных: " . count($seen) . " / всего: " . count($items) . "\n";

echo "\n=== Save + Cache ===\n";
$cache = new InstantSearcher();
$saved = $cache->saveResults($items);
echo "Saved: $saved\n";
$cached = $cache->search('w7008', 'mannfilter');
$pkCnt = 0;
foreach ($cached as $c) { if ($c->source === 'partkom') $pkCnt++; }
echo "Cache PartKom: $pkCnt\n";