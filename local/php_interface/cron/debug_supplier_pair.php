<?php
/**
 * Диагностика: почему конкретный поставщик не находит конкретную пару бренд+артикул.
 * Делает РОВНО тот же buildSearchRequest(brand, article, false), что и crossload,
 * реально стучится к поставщику и печатает сырой ответ — без всякой последующей фильтрации.
 *
 * Запуск: php debug_supplier_pair.php <supplier_code> "<brand>" "<article>"
 * Пример: php debug_supplier_pair.php partkom "Renault" "77 00 274 177"
 */

if ($argc < 4) {
    fwrite(STDERR, "Использование: php debug_supplier_pair.php <supplier_code> \"<brand>\" \"<article>\"\n");
    exit(1);
}

$code    = $argv[1];
$brand   = $argv[2];
$article = $argv[3];

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
}
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';
use Lider\Search\BrandNormalizer;

$factory   = getSupplierFactory();
$suppliers = $factory->allAvailable();

if (!isset($suppliers[$code])) {
    fwrite(STDERR, "Неизвестный или недоступный поставщик: $code\nДоступны: " . implode(', ', array_keys($suppliers)) . "\n");
    exit(1);
}
$supplier = $suppliers[$code];

echo "=== $code: brand=\"$brand\" article=\"$article\" ===\n";
echo "brand_norm=" . BrandNormalizer::normalize($brand) . " article_norm=" . BrandNormalizer::normalizeArticle($article) . "\n\n";

foreach ([false, true] as $withCrosses) {
    echo "--- withCrosses=" . ($withCrosses ? 'true' : 'false') . " ---\n";
    $req = $supplier->buildSearchRequest($brand, $article, $withCrosses);
    if (!$req) {
        echo "buildSearchRequest вернул null (поставщик недоступен либо не смог собрать запрос)\n\n";
        continue;
    }
    echo "URL: " . $req['url'] . "\n";
    if (!empty($req['body'])) {
        echo "BODY: " . substr($req['body'], 0, 2000) . "\n";
    }

    $ch = curl_init($req['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_ENCODING       => '',
    ]);
    if (($req['method'] ?? 'GET') === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
    }
    $t0 = microtime(true);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "HTTP: $http  time=" . round(microtime(true) - $t0, 2) . "s  err=" . ($err ?: '-') . "\n";
    echo "RAW RESPONSE (первые 3000 символов):\n" . substr((string)$body, 0, 3000) . "\n";

    if ($body) {
        try {
            $items = $supplier->parseSearchResponse($body, $brand, $article);
            echo "\nparseSearchResponse вернул позиций: " . count($items) . "\n";
            foreach (array_slice($items, 0, 5) as $it) {
                echo "  - brand={$it->brand} article={$it->article} price={$it->price} qty={$it->quantity} sched=" . ($it->isSched ? '1' : '0') . "\n";
            }
        } catch (\Throwable $e) {
            echo "parseSearchResponse ИСКЛЮЧЕНИЕ: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}
