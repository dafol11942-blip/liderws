<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

class TatpartsConnector implements SupplierInterface
{
    private string $user     = 'lider16';
    private string $password = "'8dTpDU8}Myr)*&";
    private string $provider = 'tatparts_ru';
    private string $supLogin = 'lider-16@bk.ru';
    private string $supPass  = 'elabuga16';
    private string $baseUrl  = 'https://service.tradesoft.ru/3/';
    private int    $timeout  = 8;

    public function __construct(array $config = [])
    {
        // USER и PASSWORD — это авторизация API Tradesoft
        $this->user     = $config['USER']     ?? $this->user;
        $this->password = $config['PASSWORD'] ?? $this->password;
        // SUP_LOGIN и SUP_PASS — авторизация конкретного поставщика внутри контейнера
        $this->supLogin = $config['SUP_LOGIN'] ?? $config['LOGIN'] ?? $this->supLogin;
        $this->supPass  = $config['SUP_PASS']  ?? $this->supPass;
        $this->provider = $config['PROVIDER'] ?? $this->provider;
        $this->baseUrl  = $config['BASE_URL']  ?? $this->baseUrl;
        $this->timeout  = $config['TIMEOUT']   ?? $this->timeout;
    }

    public function getCode(): string       { return 'tatparts'; }
    public function getName(): string       { return 'ТатПартс'; }
    public function getWarehousePrefix(): string { return 'ttp'; }
    public function maskWarehouseName(string $realName): string { return $this->generateWarehouseCode($realName); }
    public function isAvailable(): bool     { return true; }

    public function searchBrands(string $article): array
    {
        $body = $this->buildBrandsBody($article);
        $resp = $this->execPost($body);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsBody(string $article): array
    {
        return [
            'service'   => 'provider',
            'action'    => 'getProducerList',
            'user'      => $this->user,
            'password'  => $this->password,
            'timeLimit' => $this->timeout,
            'container' => [[
                'provider' => $this->provider,
                'login'    => $this->supLogin,
                'password' => $this->supPass,
                'code'     => $article,
            ]]
        ];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        $body = $this->buildBrandsBody($article);
        return [
            'url'     => $this->baseUrl,
            'headers' => ['Content-Type: application/json', 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => json_encode($body),
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $brands;
        foreach (($data['container'] ?? []) as $cont) {
            foreach (($cont['data'] ?? []) as $item) {
                $b = trim((string)($item['producer'] ?? ''));
                $d = trim((string)($item['caption'] ?? ''));
                if ($b === '' || $requestArticle === '') continue;
                $key = $b . '|' . $requestArticle;
                if (!isset($brands[$key])) {
                    $brands[$key] = ['brand' => $b, 'article' => $requestArticle, 'article_fix' => $requestArticle, 'description' => $d];
                }
            }
        }
        return array_values($brands);
    }

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $body = $this->buildSearchBody($brand, $article);
        $resp = $this->execPost($body);
        $items = $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
        if (!empty($items)) return $items;

        if ($brand !== '' || $article !== '') {
            $brands = $this->searchBrands($article);
            $brandLower = mb_strtolower(trim($brand));
            foreach ($brands as $br) {
                $b = $br['brand'];
                if ($brandLower === '' || 
                    mb_stripos($b, $brandLower) !== false || 
                    mb_stripos($brandLower, $b) !== false ||
                    (mb_strlen($b) >= 3 && mb_strlen($brandLower) >= 3 && 
                     mb_substr(mb_strtolower($b), 0, 3) === mb_substr($brandLower, 0, 3))) {
                    $body2 = $this->buildSearchBody($b, $article);
                    $resp2 = $this->execPost($body2);
                    $items2 = $resp2 !== null ? $this->parseSearchResponse($resp2, $b, $article) : [];
                    if (!empty($items2)) return $items2;
                }
            }
        }

        return [];
    }

    public function buildSearchBody(string $brand, string $article): array
    {
        $container = [
            'provider' => $this->provider,
            'login'    => $this->supLogin,
            'password' => $this->supPass,
            'code'     => $article,
        ];
        if ($brand !== '') $container['producer'] = $brand;
        return [
            'service'   => 'provider',
            'action'    => 'getPriceList',
            'user'      => $this->user,
            'password'  => $this->password,
            'timeLimit' => $this->timeout,
            'container' => [$container]
        ];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        $body = $this->buildSearchBody($brand, $article);
        return [
            'url'     => $this->baseUrl,
            'headers' => ['Content-Type: application/json', 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => json_encode($body),
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $results;
        foreach (($data['container'] ?? []) as $cont) {
            foreach (($cont['data'] ?? []) as $item) {
                $r = $this->buildResultItem($item, $brand, $article);
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                $results[] = $r;
            }
        }
        usort($results, fn($a,$b) => (!$a->isSched && $b->isSched) ? -1 : (($a->isSched && !$b->isSched) ? 1 : $a->price <=> $b->price));
        return $results;
    }

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) if (!$item->isSched && $item->price > 0) return $item;
        return $items[0] ?? null;
    }

    public function search(string $query): array
    {
        $results = [];
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;
        $brands = $this->searchBrands($query);
        $seed = []; foreach (array_slice($brands,0,10) as $br) $seed[]=$br;
        $seen = [];
        foreach ($seed as $br) {
            $items = $this->searchByBrandArticle($br['brand'], $br['article_fix']);
            foreach ($items as $r) { $dk=$r->getDedupeKey(); if (!isset($seen[$dk])) { $seen[$dk]=true; $results[]=$r; } }
            if (count($results)>=30) break;
        }
        usort($results, fn($a,$b)=>$a->price<=>$b->price);
        return array_slice($results,0,30);
    }

    public function supportsCrossSearch(): bool { return false; }
    public function getSearchTimeout(): int { return $this->timeout; }

    private function execPost(array $body): ?string
    {
        $json = json_encode($body);
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$json, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
            CURLOPT_TIMEOUT=>$this->timeout+5, CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0,
        ]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        return ($err || !$resp) ? null : $resp;
    }

    private function buildResultItem(array $item, string $db, string $da): SearchResultItem
    {
        $p=(float)str_replace(',','.',(string)($item['price']??'0'));
        $q=(int)($item['rest']??0); $d=max(1,(int)($item['deliverydays_min']??1));
        $r=new SearchResultItem();
        $r->source='tatparts'; $r->article=(string)($item['code']??$da); $r->brand=(string)($item['producer']??$db);
        $r->name=(string)($item['caption']??''); $r->price=$p; $r->quantity=$q;
        $r->deliveryDays=$d; $r->deliveryPeriod=$d*24; $r->warehouse=(string)($item['direction']??'');
        $r->stockId=(string)($item['itemHash']??''); $r->supplierName='ТатПартс';
        $r->isSched=($q<=0); $r->multiplicity=max(1,(int)($item['packing']??1)); $r->unit='шт.';
        $r->returnable=($item['return']??'')==='possible'; $r->raw=$item;
        return $r;
    }

    private function generateWarehouseCode(string $n): string
    {
        static $m=['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>''];
        $t='';foreach(mb_str_split(mb_strtolower(trim($n))) as $c)$t.=$m[$c]??$c;
        return 'ttp_'.str_pad(substr(preg_replace('/[^a-z0-9]/','',$t),0,3),3,'x');
    }
}
