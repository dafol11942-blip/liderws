<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';

use Lider\Supplier\PartKomConnector;

// Прямой curl-запрос
$login = 'lider16';
$password = '8dTpDU8}Myr)*&';
$auth = base64_encode($login . ':' . $password);

echo "=== Direct curl: W7008 (no brand) ===\n";
$url = 'https://ws.part-kom.ru/v4/search/offers?number=W7008&find_substitutes=0';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $auth, 'Accept: application/json'],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP: $code, Error: " . ($err ?: 'none') . "\n";
echo "Response (first 1000 chars):\n" . substr($resp, 0, 1000) . "\n";
echo "Full length: " . strlen($resp) . "\n";

echo "\n=== Direct curl: W7008 with maker_id ===\n";
$makerUrl = 'https://ws.part-kom.ru/v4/search/brands';
$ch2 = curl_init($makerUrl);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $auth, 'Accept: application/json'],
    CURLOPT_TIMEOUT => 10,
]);
$brandsResp = curl_exec($ch2);
curl_close($ch2);
echo "Brands (first 500): " . substr($brandsResp, 0, 500) . "\n";

// Найдём MANN
$brands = json_decode($brandsResp, true);
if (is_array($brands)) {
    foreach ($brands as $b) {
        if (is_array($b) && isset($b['name']) && stripos($b['name'], 'MANN') !== false) {
            echo "  FOUND: id={$b['id']} name={$b['name']}\n";
        }
    }
}