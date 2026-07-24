<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';

use Lider\Supplier\PartKomConnector;
use Lider\Search\BrandNormalizer;
use Lider\Search\InstantSearcher;

$pk = new PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'8dTpDU8}Myr)*&']);

echo "=== PartKom searchByBrandArticle('MANN-FILTER', 'W7008') ===\n";
$items = $pk->searchByBrandArticle('MANN-FILTER', 'W7008');
echo "Items: " . count($items) . "\n";
foreach ($items as $i) {
    echo "  brand={$i->brand} / art={$i->article} / price={$i->price} / qty={$i->quantity} / stockId={$i->stockId}\n";
}

echo "\n=== PartKom searchByBrandArticle('', 'W7008') ===\n";
$items2 = $pk->searchByBrandArticle('', 'W7008');
echo "Items: " . count($items2) . "\n";
$mann = 0;
foreach ($items2 as $i) {
    $bn = BrandNormalizer::normalize($i->brand);
    if ($bn === 'mannfilter') $mann++;
}
echo "MANN items in nobrand: $mann\n";

echo "\n=== Save nobrand results to cache ===\n";
$cache = new InstantSearcher();
$saved = $cache->saveResults($items2);
echo "Saved: $saved\n";

echo "\n=== Cache check ===\n";
$cached = $cache->search('w7008', 'mannfilter');
echo "Total w7008+mannfilter: " . count($cached) . "\n";
$pkCount = 0;
foreach ($cached as $c) { if ($c->source === 'partkom') $pkCount++; }
echo "PartKom: $pkCount\n";