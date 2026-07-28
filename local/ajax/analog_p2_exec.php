<?php
/**
 * Фоновое выполнение Phase 2 (UMAPI-кроссы).
 * Вызывается через exec из analog_search.php.
 */
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '60');
@ini_set('display_errors', 0);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';

if (php_sapi_name() === 'cli') {
    $p2Hash = $argv[1] ?? '';
} else {
    $p2Hash = $_REQUEST['hash'] ?? '';
}

if (empty($p2Hash)) exit;

$p2File = '/var/www/u3564357/data/www/liderws.ru/upload/cache/search/p2/' . $p2Hash . '.json';
if (!file_exists($p2File)) exit;

$data = json_decode(file_get_contents($p2File), true);
if (!$data || empty($data['umapiAnalogs'])) exit;

require_once '/var/www/u3564357/data/www/liderws.ru/bitrix/modules/main/include/prolog_before.php';
require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/BrandNormalizer.php';
require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/SearchResultItem.php';
require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/Stage2/FullSearchLauncher.php';
require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/Common/MultiCurlExecutor.php';
require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Supplier/SupplierInterface.php';
require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Supplier/SupplierFactory.php';
foreach (['Moskvorechie','Rossko','PartKom','Autoeuro','Berg','Ixora','ShateM','Tatparts','Autoruss','Autopiter'] as $c) {
    require_once '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Supplier/' . $c . 'Connector.php';
}

use Lider\Search\Stage2\FullSearchLauncher;

function getP2Factory(): \Lider\Supplier\SupplierFactory {
    $f = new \Lider\Supplier\SupplierFactory();
    $f->register(new \Lider\Supplier\MoskvorechieConnector(['API_KEY'=>'2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf']));
    $f->register(new \Lider\Supplier\RosskoConnector(['KEY1'=>'d6907f0f857524815255b74cda86fe9b','KEY2'=>'a514b4c11299686d7cfe8fd3563d1c58','DELIVERY_ID'=>'000000002','ADDRESS_ID'=>'71520']));
    $f->register(new \Lider\Supplier\BergConnector(['API_KEY'=>'9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36','ADDRESS_ID'=>31173]));
    $f->register(new \Lider\Supplier\AutoeuroConnector(['API_KEY'=>'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX','DELIVERY_KEY'=>'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA']));
    $f->register(new \Lider\Supplier\PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'LidGates16']));
    $f->register(new \Lider\Supplier\IxoraConnector(['AUTH_CODE'=>'460880B0988C8C204B2DD392EC81611D','TIMEOUT'=>8]));
    $f->register(new \Lider\Supplier\TatpartsConnector());
    $f->register(new \Lider\Supplier\AutorussConnector(['LOGIN'=>'Lider-16@bk.ru','PASSWORD_MD5'=>'00fd3781d2cfdf0d971b57fa7397cfac']));
    $f->register(new \Lider\Supplier\AutopiterConnector(['USER_ID'=>'165286','PASSWORD'=>'LidGates16']));
    return $f;
}

try {
    $launcher = new FullSearchLauncher(getP2Factory());

    // Новый сигнатура: executePhase2(array $umapiAnalogs, float $deadline)
    $p2Results = $launcher->executePhase2($data['umapiAnalogs']);

    // Сохраняем результат
    $data['p2_results'] = array_map(function($item) {
        return [
            'source' => $item->source,
            'article' => $item->article,
            'brand' => $item->brand,
            'name' => $item->name,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'warehouse' => $item->warehouse,
            'stockId' => $item->stockId,
            'supplierName' => $item->supplierName,
            'isSched' => $item->isSched,
            'deliveryDays' => $item->deliveryDays,
            'deliveryPeriod' => $item->deliveryPeriod ?? 0,
            'multiplicity' => $item->multiplicity ?? 1,
            'unit' => $item->unit ?? 'шт.',
        ];
    }, $p2Results);
    $data['done'] = true;
    $data['p2_count'] = count($p2Results);
    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));

    @file_put_contents(
        '/var/www/u3564357/data/www/liderws.ru/upload/logs/p2_exec_' . date('Y-m-d') . '.log',
        '[' . date('H:i:s') . '] ' . $p2Hash . ' done: +' . count($p2Results) . " items\n",
        FILE_APPEND
    );
} catch (\Throwable $e) {
    $data['error'] = $e->getMessage();
    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
}