<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class AutorussConnector implements SupplierInterface
{
    private string $login;
    private string $passwordMd5;
    private string $baseUrl;
    private int $timeout;
    private bool $lastWithCrosses = false;

    public function __construct(array $config = [])
    {
        $this->login       = $config['LOGIN']       ?? '';
        $this->passwordMd5 = $config['PASSWORD_MD5'] ?? '';
        $this->baseUrl     = $config['BASE_URL']     ?? 'https://autorus.public.api.abcp.ru';
        $this->timeout     = $config['TIMEOUT']      ?? 10;
    }

    public function getCode(): string       { return 'autoruss'; }
    public function getName(): string       { return 'Авторусь'; }
    public function getWarehousePrefix(): string { return 'ar'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->login) && !empty($this->passwordMd5);
    }

    // ==================== АВТОРИЗАЦИЯ ====================

    private function authQuery(): string
    {
        return 'userlogin=' . urlencode($this->login)
            . '&userpsw=' . urlencode($this->passwordMd5);
    }

    // ==================== БРЕНДЫ ====================

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) return null;
        $url = $this->baseUrl . '/search/brands/?'
            . $this->authQuery()
            . '&number=' . urlencode($article)
            . '&useOnlineStocks=1';
        return [
            'url'     => $url,
            'headers' => ['Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $brands;

        $article = trim($requestArticle);
        foreach ($data as $item) {
            if (!is_array($item)) continue;
            $b  = trim((string)($item['brand'] ?? ''));
            $n  = trim((string)($item['number'] ?? ''));
            $nf = trim((string)($item['numberFix'] ?? $n));
            if (!$b || !$nf) continue;

            $key = mb_strtolower($b) . '|' . mb_strtolower($nf);
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $b,
                    'article'     => $article,
                    'article_nr'  => $nf,
                    'description' => (string)($item['description'] ?? ''),
                ];
            }
        }
        return array_values($brands);
    }

    // ==================== ПРЕДЛОЖЕНИЯ ====================

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $req = $this->buildSearchRequest($brand, $article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) return null;
        $this->lastWithCrosses = $withCrosses;

        $url = $this->baseUrl . '/search/articles/?'
            . $this->authQuery()
            . '&number=' . urlencode($article)
            . '&brand=' . urlencode($brand)
            . '&useOnlineStocks=1';

        // withOutAnalogs: если кроссы НЕ нужны → исключаем аналоги
        if (!$withCrosses) {
            $url .= '&withOutAnalogs=1';
        }

        return [
            'url'     => $url,
            'headers' => ['Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $results;

        $normBrand = BrandNormalizer::normalize($brand);
        $normArt   = BrandNormalizer::normalizeArticle($article);
        $withCrosses = $this->lastWithCrosses;

        foreach ($data as $item) {
            if (!is_array($item)) continue;

            $itemBrand  = trim((string)($item['brand'] ?? ''));
            $itemNumber = (string)($item['number'] ?? '');
            $itemNumberFix = (string)($item['numberFix'] ?? $itemNumber);

            // Фильтрация по бренду — только для точного поиска
            if (!$withCrosses) {
                if ($normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand) {
                    continue;
                }
                if ($normArt !== '' && $itemNumberFix !== ''
                    && BrandNormalizer::normalizeArticle($itemNumberFix) !== $normArt) {
                    continue;
                }
            } else {
                // Cross: выкидываем "однофамильцев" — тот же артикул, другой бренд
                if ($normArt !== '' && $itemNumberFix !== ''
                    && BrandNormalizer::normalizeArticle($itemNumberFix) === $normArt
                    && $normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand
                ) {
                    continue;
                }
            }

            // availability: >0 = кол-во; -1,-2,-3 = неточное; -10 = под заказ; 0 = нет
            $avail = (int)($item['availability'] ?? 0);
            if ($avail > 0) {
                $qty     = $avail;
                $isSched = false;
            } elseif ($avail < 0 && $avail > -10) {
                // -1, -2, -3 — неточное наличие, считаем как 1+
                $qty     = max(1, abs($avail));
                $isSched = false;
            } else {
                // -10 (под заказ), 0, или другое → под заказ
                $qty     = 0;
                $isSched = true;
            }

            $deliveryPeriod = (int)($item['deliveryPeriod'] ?? 0);
            $deliveryPeriodMax = (int)($item['deliveryPeriodMax'] ?? 0);

            $r = new SearchResultItem();
            $r->source       = $this->getCode();
            $r->article      = $itemNumberFix !== '' ? $itemNumberFix : $article;
            $r->brand        = $itemBrand !== '' ? $itemBrand : $brand;
            $r->name         = (string)($item['description'] ?? '');
            $r->price        = (float)($item['price'] ?? 0);
            $r->quantity     = $qty;
            $r->warehouse    = 'Авторусь: ' . ((string)($item['supplierDescription'] ?? $item['distributorId'] ?? 'Склад'));
            $r->stockId      = (string)($item['supplierCode'] ?? '') . '|' . (string)($item['itemKey'] ?? '');
            $r->supplierName = $this->getName();
            $r->isSched      = $isSched;
            $r->multiplicity = max(1, (int)($item['packing'] ?? 1));
            $r->unit         = 'шт.';
            $r->returnable   = empty($item['noReturn']);

            // Срок доставки (+48 часов запас)
            $deliveryPeriod += 48;
            $deliveryPeriodMax += 48;
            if ($deliveryPeriod > 0) {
                $r->deliveryPeriod = $deliveryPeriod;
                $now = time();
                if ($isSched) {
                    $r->deliveryDays = max(1, (int)ceil($deliveryPeriod / 24));
                } else {
                    $r->deliveryDays = (int)ceil($deliveryPeriod / 24);
                    $r->raw['deliveryDateFrom'] = date('Y-m-d H:i:s', $now + $deliveryPeriod * 3600);
                    if ($deliveryPeriodMax > $deliveryPeriod) {
                        $r->raw['deliveryDateTo'] = date('Y-m-d H:i:s', $now + $deliveryPeriodMax * 3600);
                    }
                }
            }    

            $r->raw = array_merge($r->raw ?? [], [
                'deliveryPeriod'     => $deliveryPeriod,
                'deliveryPeriodMax'  => $deliveryPeriodMax,
                'supplierCode'       => $item['supplierCode'] ?? null,
                'itemKey'            => $item['itemKey'] ?? null,
                'distributorId'      => $item['distributorId'] ?? null,
                'lastUpdateTime'     => $item['lastUpdateTime'] ?? null,
                'deliveryProbability'=> $item['deliveryProbability'] ?? null,
                'noReturn'           => $item['noReturn'] ?? null,
                'isAnalog'           => $item['isAnalog'] ?? null,
            ]);

            if ($r->price <= 0 && $r->quantity <= 0) continue;
            $results[] = $r;
            if (count($results) >= 160) break;
        }

        // Дедупликация
        $seen = [];
        $unique = [];
        foreach ($results as $res) {
            $dk = ($res->stockId ?: '') . '|' . $res->price;
            if (!isset($seen[$dk])) {
                $seen[$dk] = true;
                $unique[] = $res;
            }
        }

        // Сортировка: сначала со склада, потом по цене
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });

        return array_slice($unique, 0, 120);
    }

    // ==================== ДЕТАЛЬНАЯ ИНФОРМАЦИЯ ====================

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) {
            if (!$item->isSched && $item->price > 0) return $item;
        }
        return $items[0] ?? null;
    }

    // ==================== ПОЛНЫЙ ПОИСК ====================

    public function search(string $query): array
    {
        $results = [];
        if (!$this->isAvailable()) return $results;
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        // Сначала получаем бренды
        $brands = $this->searchBrands($query);
        $brands = array_slice($brands, 0, 10);

        foreach ($brands as $br) {
            try {
                $items = $this->searchByBrandArticle($br['brand'], $br['article_nr']);
                $results = array_merge($results, array_slice($items, 0, 3));
            } catch (\Throwable $e) {
                $this->log("Brand {$br['brand']} error: " . $e->getMessage());
            }
        }

        // Дедупликация
        $seen = []; $unique = [];
        foreach ($results as $item) {
            $key = $item->getDedupeKey();
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $item; }
        }

        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });

        return array_slice($unique, 0, 30);
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            $this->log("HTTP {$httpCode} err={$err} url={$req['url']}");
            return null;
        }
        return $resp;
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
        while (strlen($abbr) < 3) $abbr .= 'x';
        return $this->getWarehousePrefix() . '_' . $abbr;
    }

    private function log(string $message): void
    {
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/autoruss_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    public function supportsCrossSearch(): bool
    {
        return true;
    }

    public function getSearchTimeout(): int
    {
        return 10;
    }
}