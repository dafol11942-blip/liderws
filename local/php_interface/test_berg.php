<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\InstantSearcher;

$factory = getSupplierFactory();
$berg = $factory->get('berg');

echo "=== Berg searchByBrandArticle ===\n";
$items = $berg->searchByBrandArticle('LYNXauto', 'LC331');
echo "Items from Berg: " . count($items) . "\n";
foreach ($items as $item) {
    echo "  brand={$item->brand} / art={$item->article} / price={$item->price} / qty={$item->quantity} / wh={$item->warehouse} / stockId={$item->stockId} / isSched=" . ($item->isSched?'Y':'N') . "\n";
}

echo "\n=== FullSearchLauncher ===\n";
$launcher = new FullSearchLauncher($factory);
$results = $launcher->launch('LYNXauto', 'LC331', [], 'lynxauto|lc331', null, 15.0);
echo "Total: " . count($results) . "\n";
$by = [];
foreach ($results as $r) { if (!isset($by[$r->source])) $by[$r->source] = 0; $by[$r->source]++; }
foreach ($by as $s => $c) echo "  $s: $c\n";

echo "\n=== Save to cache ===\n";
$cache = new InstantSearcher();
$saved = $cache->saveResults($results);
echo "Saved: $saved\n";

echo "\n=== Cache after save ===\n";
$cached = $cache->search('lc331', 'lynxauto');
$bc = 0; foreach ($cached as $c) { if ($c->source === 'berg') $bc++; }
echo "Total cached: " . count($cached) . " (berg: $bc)\n";