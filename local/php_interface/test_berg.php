<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\InstantSearcher;
use Lider\Supplier\SupplierFactory;
use Lider\Supplier\MoskvorechieConnector;
use Lider\Supplier\RosskoConnector;
use Lider\Supplier\BergConnector;
use Lider\Supplier\AutoeuroConnector;
use Lider\Supplier\PartKomConnector;
use Lider\Supplier\IxoraConnector;
use Lider\Supplier\TatpartsConnector;

$factory = new SupplierFactory();
$factory->register(new MoskvorechieConnector(['API_KEY'=>'2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf']));
$factory->register(new RosskoConnector(['KEY1'=>'d6907f0f857524815255b74cda86fe9b','KEY2'=>'a514b4c11299686d7cfe8fd3563d1c58','DELIVERY_ID'=>'000000002','ADDRESS_ID'=>'71520']));
$factory->register(new BergConnector(['API_KEY'=>'9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36','ADDRESS_ID'=>31173]));
$factory->register(new AutoeuroConnector(['API_KEY'=>'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX','DELIVERY_KEY'=>'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA']));
$factory->register(new PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'8dTpDU8}Myr)*&']));
$factory->register(new IxoraConnector(['AUTH_CODE'=>'460880B0988C8C204B2DD392EC81611D','TIMEOUT'=>8]));
$factory->register(new TatpartsConnector(['LOGIN'=>'lider-16@bk.ru','PASSWORD'=>"'8dTpDU8}Myr)*&",'TIMEOUT'=>10]));

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