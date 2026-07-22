<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class IxoraConnector implements SupplierInterface
{
    private string $authCode;
    private string $endpoint;
    private int $timeout;
    private string $namespace = 'http://ws.ixora-auto.ru/';

    public function __construct(array $config = [])
    {
        $this->authCode = (string)($config['AUTH_CODE'] ?? $config['API_KEY'] ?? '');
        $this->endpoint = rtrim((string)($config['ENDPOINT'] ?? 'http://ws.ixora-auto.ru/soap/ApiService.asmx'), '/');
        $this->timeout  = (int)($config['TIMEOUT'] ?? 8);
    }

    public function getCode(): string { return 'ixora'; }
    public function getName(): string { return 'Иксора'; }
    public function getWarehousePrefix(): string { return 'ixr'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return $this->authCode !== '';
    }

    // ==================== ЭТАП 1: БРЕНДЫ ====================

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) {
            return [];
        }
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $body = $this->soapEnvelope('GetMakers', [
            'Number'   => trim($article),
            'AuthCode' => $this->authCode,
        ]);

        return [
            'url'     => $this->endpoint,
            'headers' => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . $this->namespace . 'GetMakers',
            ],
            'method'  => 'POST',
            'body'    => $body,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $article = trim($requestArticle);
        $xml = @simplexml_load_string($responseBody);
        if ($xml === false || $xml === null) {
            $this->log('parseBrandsResponse: bad XML');
            return $brands;
        }

        $nodes = $xml->xpath('//*[local-name()="MakerInfo"]') ?: [];
        foreach ($nodes as $node) {
            $name = trim((string)($node->xpath('*[local-name()="name"]')[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name) . '|' . mb_strtolower($article);
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $name,
                    'article'     => $article,
                    'article_nr'  => $article,
                    'description' => '',
                ];
            }
        }

        return array_values($brands);
    }

    // ==================== ЭТАП 2: ПРЕДЛОЖЕНИЯ ====================

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $req = $this->buildSearchRequest($brand, $article, false);
        if (!$req) {
            return [];
        }
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    /**
     * @param bool $withCrosses true = SubstFilter=All (оригинал+аналоги), false = Originals
     */
    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $subst = $withCrosses ? 'All' : 'Originals';

        $body = $this->soapEnvelope('FindExt', [
            'Number'           => trim($article),
            'Maker'            => trim($brand),
            'StockOnly'        => 'false',
            'SubstFilter'      => $subst,
            'ConditionsFilter' => 'All',
            'AuthCode'         => $this->authCode,
        ]);

        return [
            'url'     => $this->endpoint,
            'headers' => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . $this->namespace . 'FindExt',
            ],
            'method'  => 'POST',
            'body'    => $body,
            // помечаем режим, чтобы parse мог понять контекст при необходимости
            '_ixora_with_crosses' => $withCrosses ? 1 : 0,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $xml = @simplexml_load_string($responseBody);
        if ($xml === false || $xml === null) {
            $this->log('parseSearchResponse: bad XML');
            return $results;
        }

        // fault?
        $fault = $xml->xpath('//*[local-name()="Fault"]');
        if ($fault) {
            $msg = (string)($xml->xpath('//*[local-name()="faultstring"]')[0] ?? 'SOAP Fault');
            $this->log('SOAP Fault: ' . $msg);
            return $results;
        }

        $normBrand = BrandNormalizer::normalize($brand);
        $normArt   = BrandNormalizer::normalizeArticle($article);

        // Если в запросе был Maker и Originals — API всё равно может отдать лишнее.
        // Exact-режим: оставляем совпадение brand+article.
        // Cross-режим (много разных brand): не режем по brand — stage2 сам разложит exact/analog.
        $details = $xml->xpath('//*[local-name()="DetailInfo"]') ?: [];

        // эвристика cross: если среди ответа >1 нормализованного бренда и целевой бренд задан —
        // всё равно отдаём все валидные позиции (как PartKom с substitutes).
        $brandSet = [];
        foreach ($details as $d) {
            $b = trim((string)($d->xpath('*[local-name()="maker"]')[0] ?? ''));
            if ($b !== '') {
                $brandSet[BrandNormalizer::normalize($b)] = true;
            }
        }
        $isCrossResponse = count($brandSet) > 1;

        foreach ($details as $d) {
            $itemBrand   = trim((string)($d->xpath('*[local-name()="maker"]')[0] ?? ''));
            $itemNumber  = trim((string)($d->xpath('*[local-name()="number"]')[0] ?? ''));
            $itemName    = trim((string)($d->xpath('*[local-name()="name"]')[0] ?? ''));
            $qtyRaw      = trim((string)($d->xpath('*[local-name()="quantity"]')[0] ?? '0'));
            $qty         = $this->parseQty($qtyRaw);
            $price       = (float)str_replace(',', '.', (string)($d->xpath('*[local-name()="price"]')[0] ?? 0));
            $lot         = max(1, (int)($d->xpath('*[local-name()="lotquantity"]')[0] ?? 1));
            $days        = (int)($d->xpath('*[local-name()="days"]')[0] ?? 0);
            $daysW       = (int)($d->xpath('*[local-name()="dayswarranty"]')[0] ?? 0);
            $region      = trim((string)($d->xpath('*[local-name()="region"]')[0] ?? ''));
            $group       = trim((string)($d->xpath('*[local-name()="group"]')[0] ?? ''));
            $dateArrival = trim((string)($d->xpath('*[local-name()="datearrival"]')[0] ?? ''));
            $retPeriod   = (int)($d->xpath('*[local-name()="returnperiod"]')[0] ?? 0);
            $retCond     = trim((string)($d->xpath('*[local-name()="returnconditions"]')[0] ?? ''));
            $retCondId   = (int)($d->xpath('*[local-name()="returnconditionsid"]')[0] ?? 0);
            $orderRef    = trim((string)($d->xpath('*[local-name()="orderreference"]')[0] ?? ''));
            $estimation  = trim((string)($d->xpath('*[local-name()="estimation"]')[0] ?? ''));

            if ($itemBrand === '' || $itemNumber === '') {
                continue;
            }
            if ($price <= 0 && $qty <= 0) {
                continue;
            }

            // В exact-ответе (один бренд / Originals) фильтруем строго.
            if (!$isCrossResponse) {
                if ($normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand) {
                    continue;
                }
                // article иногда с разделителями — сравниваем нормализованно
                if ($normArt !== '' && BrandNormalizer::normalizeArticle($itemNumber) !== $normArt) {
                    // для Originals API обычно тот же номер; аналоги отсекаем
                    if (strcasecmp($group, 'Analog') === 0 || strcasecmp($group, 'ReplacmentOriginal') === 0) {
                        continue;
                    }
                    // если group=Original, но номер другой — тоже пропустим
                    continue;
                }
            }

            $r = new SearchResultItem();
            $r->source       = $this->getCode();
            $r->article      = $itemNumber;
            $r->brand        = $itemBrand;
            $r->name         = $itemName;
            $r->price        = $price;
            $r->quantity     = $qty;
            $r->multiplicity = $lot;
            $r->unit         = 'шт.';
            $r->warehouse    = $region !== '' ? $region : 'Иксора';
            $r->stockId      = $orderRef !== '' ? $orderRef : md5($itemBrand . '|' . $itemNumber . '|' . $region . '|' . $price . '|' . $days);
            $r->supplierName = $this->getName();
            // qty>0 = реальное наличие на складе поставщика (даже если days>0)
            $r->isSched      = ($qty <= 0);
            $r->returnable   = $this->isReturnable($retPeriod, $retCondId, $retCond);

            // Срок
            $deliveryDays = $days > 0 ? $days : ($daysW > 0 ? $daysW : 0);
            $r->deliveryDays   = $deliveryDays;
            $r->deliveryPeriod = $deliveryDays > 0 ? $deliveryDays * 24 : 0;

            // Лёгкий raw: не тащим огромные SOAP-поля в кеш/память
            $raw = [
                'group'              => $group,
                'returnperiod'       => $retPeriod,
                'returnconditionsid' => $retCondId ?? 0,
                'returnconditions'   => $retCond,
                'days'               => $days,
                'datearrival'        => $dateArrival,
            ];

            if ($dateArrival !== '') {
                // 2026-07-21T19:00:00+03:00
                $ts = strtotime($dateArrival);
                if ($ts) {
                    $raw['deliveryDateFrom'] = date('c', $ts);
                    // окно +2ч для отображения
                    $raw['deliveryDateTo'] = date('c', $ts + 2 * 3600);
                    if ($r->deliveryDays === null || $r->deliveryDays === 0) {
                        $now = time();
                        if (date('Y-m-d', $ts) === date('Y-m-d', $now)) {
                            $r->deliveryDays = 0;
                        } else {
                            $r->deliveryDays = max(1, (int)ceil(($ts - strtotime('today')) / 86400));
                        }
                        $r->deliveryPeriod = max(0, (int)(($ts - $now) / 3600));
                    }
                }
            }

            $r->raw = $raw;
            $results[] = $r;
            // early stop on huge cross payloads
            if ($isCrossResponse && count($results) >= 160) {
                break;
            }
        }

        // дедуп
        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $dk = ($item->stockId ?: '') . '|' . $item->price . '|' . $item->quantity;
            if (isset($seen[$dk])) {
                continue;
            }
            $seen[$dk] = true;
            $unique[] = $item;
        }

        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });

        // защита от гигантских cross-ответов
        // Cross-ответ Ixora бывает 1000+ позиций — режем жёстко, иначе OOM в кеше stage2
        $limit = $isCrossResponse ? 120 : 80;
        return array_slice($unique, 0, $limit);
    }

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) {
            if (!$item->isSched && $item->price > 0) {
                return $item;
            }
        }
        return $items[0] ?? null;
    }

    public function search(string $query): array
    {
        $results = [];
        if (!$this->isAvailable()) {
            return $results;
        }
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return $results;
        }

        $brands = $this->searchBrands($query);
        $brands = array_slice($brands, 0, 8);
        foreach ($brands as $br) {
            try {
                $items = $this->searchByBrandArticle($br['brand'], $br['article_nr'] ?? $query);
                $results = array_merge($results, array_slice($items, 0, 5));
            } catch (\Throwable $e) {
                $this->log('search brand error: ' . $e->getMessage());
            }
        }

        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $k = $item->getDedupeKey() . '|' . $item->stockId;
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $unique[] = $item;
            }
        }
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return array_slice($unique, 0, 40);
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function parseQty(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '0') {
            return 0;
        }
        // >10 / >=10
        if (preg_match('/^>=?\s*(\d+)/', $val, $m)) {
            return max(1, (int)$m[1]);
        }
        // 10+ 
        if (preg_match('/^(\d+)\+$/', $val, $m)) {
            return max(1, (int)$m[1]);
        }
        // 5-10
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $val, $m)) {
            return max(1, (int)$m[1]);
        }
        if (preg_match('/(\d+)/', $val, $m)) {
            return max(0, (int)$m[1]);
        }
        return 0;
    }

    private function soapEnvelope(string $method, array $params): string
    {
        $inner = '<' . $method . ' xmlns="' . $this->namespace . '">';
        foreach ($params as $k => $v) {
            if ($v === null) {
                continue;
            }
            // boolean уже строкой true/false
            $inner .= '<' . $k . '>' . htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $k . '>';
        }
        $inner .= '</' . $method . '>';

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $inner . '</soap:Body>'
            . '</soap:Envelope>';
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $req['body'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $http !== 200 || $resp === false || $resp === '') {
            $this->log("HTTP {$http} err={$err}");
            return null;
        }
        return $resp;
    }

    
    private function isReturnable(int $returnPeriod, int $returnConditionId, string $returnConditions): bool
    {
        $txt = mb_strtolower(trim($returnConditions));

        // id=1 в справочнике Ixora = "Возврат невозможен"
        if ($returnConditionId === 1) {
            return false;
        }

        if ($txt !== '') {
            // явный запрет
            if (str_contains($txt, 'невозможен')
                || str_contains($txt, 'невозможна')
                || str_contains($txt, 'невозможн')
                || str_contains($txt, 'невозврат')
                || str_contains($txt, 'без возврата')
                || str_contains($txt, 'возврату не подлежит')
            ) {
                return false;
            }
        }

        // period > 0 или известные "можно вернуть" id
        if ($returnPeriod > 0) {
            return true;
        }

        // id 2..10 — варианты гарантированного/ограниченного возврата
        if ($returnConditionId >= 2) {
            return true;
        }

        // нет данных — безопаснее считать невозвратным? для фильтров лучше false
        // но у части позиций period=0 и пустой текст. Тогда false.
        return false;
    }

    private function generateWarehouseCode(string $name): string
    {
        static $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            ' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>'',
        ];
        $lower = mb_strtolower(trim($name));
        $translit = '';
        foreach (mb_str_split($lower) as $char) {
            $translit .= $map[$char] ?? $char;
        }
        $clean = preg_replace('/[^a-z0-9]/', '', $translit);
        $abbr = substr($clean, 0, 3);
        while (strlen($abbr) < 3) {
            $abbr .= 'x';
        }
        return $this->getWarehousePrefix() . '_' . $abbr;
    }

    private function log(string $message): void
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        $file = $root . '/upload/logs/ixora_' . date('Y-m-d') . '.log';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }
}
