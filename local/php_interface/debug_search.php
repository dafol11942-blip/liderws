<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';

// Грузим всё как на боевом сайте
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/SearchResultItem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/SupplierInterface.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/SupplierFactory.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/MoskvorechieConnector.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/RosskoConnector.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/PartKomConnector.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/AutoeuroConnector.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/BergConnector.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/IxoraConnector.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Supplier/ShateMConnector.php';

// Используем те же ключи, что в init.php
$factory = new \Lider\Supplier\SupplierFactory();

$factory->register(new \Lider\Supplier\MoskvorechieConnector([
    'API_KEY' => '2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf',
]));

$factory->register(new \Lider\Supplier\RosskoConnector([
    'KEY1'        => 'd6907f0f857524815255b74cda86fe9b',
    'KEY2'        => 'a514b4c11299686d7cfe8fd3563d1c58',
    'DELIVERY_ID' => '000000002',
    'ADDRESS_ID'  => '71520',
]));

$factory->register(new \Lider\Supplier\BergConnector([
    'API_KEY' => '9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36',
    'ADDRESS_ID' => 31173,
]));

$factory->register(new \Lider\Supplier\AutoeuroConnector([
    'API_KEY'      => 'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX',
    'DELIVERY_KEY' => 'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA',
]));

$factory->register(new \Lider\Supplier\PartKomConnector([
    'LOGIN'    => 'lider16',
    'PASSWORD' => 'LidGates16',
]));

$factory->register(new \Lider\Supplier\IxoraConnector([
    'AUTH_CODE' => '460880B0988C8C204B2DD392EC81611D',
    'TIMEOUT'   => 8,
]));

$brand  = 'MANN-FILTER';
$article = 'W81180';

echo "=== TEST: $brand / $article ===\n\n";

foreach ($factory->allAvailable() as $sup) {
    $code = $sup->getCode();
    $name = $sup->getName();
    echo "[$code] $name\n";
    
    $req = $sup->buildSearchRequest($brand, $article, false);
    if (!$req) {
        echo "  buildSearchRequest() → NULL\n\n";
        continue;
    }
    
    echo "  URL: " . substr($req['url'], 0, 100) . "...\n";
    
    $ch = curl_init($req['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    if (($req['method'] ?? 'GET') === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
    }
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    
    $bodyLen = strlen((string)$resp);
    echo "  HTTP: $http | Body: {$bodyLen}b";
    if ($err) echo " | cURL error: $err";
    echo "\n";
    
    if ($resp && $bodyLen < 600) {
        echo "  RAW: " . substr(preg_replace('/\s+/', ' ', (string)$resp), 0, 250) . "\n";
    }
    
    if ($http === 200 && $resp) {
        try {
            $items = $sup->parseSearchResponse($resp, $brand, $article);
            echo "  Items: " . count($items) . "\n";
            if (!empty($items)) {
                foreach (array_slice($items, 0, 3) as $it) {
                    echo "    {$it->brand}|{$it->article}|{$it->price}₽|{$it->warehouse}|qty={$it->quantity}\n";
                }
                if (count($items) > 3) echo "    ... +" . (count($items)-3) . " more\n";
            }
        } catch (\Throwable $e) {
            echo "  PARSE ERROR: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}
