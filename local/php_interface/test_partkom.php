<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';

use Lider\Supplier\PartKomConnector;
use Lider\Search\InstantSearcher;

echo "=== Test with LidGates16 ===\n";
$pk = new PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'LidGates16']);
$items = $pk->searchByBrandArticle('MANN-FILTER', 'W7008');
echo "Items with brand: " . count($items) . "\n";
foreach ($items as $i) {
    echo "  {$i->brand} / {$i->article} / {$i->price} / qty={$i->quantity} / stockId={$i->stockId}\n";
}

echo "\n=== Save to cache ===\n";
$cache = new InstantSearcher();
$saved = $cache->saveResults($items);
echo "Saved: $saved\n";

echo "\n=== Cache check ===\n";
$cached = $cache->search('w7008', 'mannfilter');
$pkCnt = 0;
foreach ($cached as $c) { if ($c->source === 'partkom') $pkCnt++; }
echo "PartKom in cache: $pkCnt\n";