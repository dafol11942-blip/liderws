<?php
namespace Lider\Search\Stage2;

use Lider\Search\Common\MultiCurlExecutor;
use Lider\Supplier\SupplierFactory;
use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class FullSearchLauncher
{
    private SupplierFactory $factory;
    public function __construct(SupplierFactory $factory) { $this->factory = $factory; }

    public function launch(
        string $brand, string $article, array $brandMap,
        string $exactKey, ?array $targetEntry, float $deadline = 15.0
    ): array {
        $suppliers = $this->factory->allAvailable();
        if (empty($suppliers)) return [];
        $allCodes = array_map(fn($s) => $s->getCode(), $suppliers);
        $normExactArt = BrandNormalizer::normalizeArticle($article);
        $normExactBrand = BrandNormalizer::normalize($brand);

        $bfs = fn($sup,$e,$fb) => ($e&&!empty($e['brands'][$sup->getCode()]))?(string)$e['brands'][$sup->getCode()]:BrandNormalizer::displayBrand($fb);
        $afs = fn($sup,$e,$fa) => ($e&&!empty($e['articles'][$sup->getCode()]))?(string)$e['articles'][$sup->getCode()]:$fa;

        // ФАЗА 1
        $p1r=[]; $p1m=[];
        foreach ($suppliers as $sup) {
            $code=$sup->getCode(); $sb=$bfs($sup,$targetEntry,$brand); $sa=$afs($sup,$targetEntry,$article);
            if ($req=$sup->buildSearchRequest($sb,$sa,false)) {
                $k=$code.':exact'; $req['_key']=$k; $req['_timeout']=$sup->getSearchTimeout(); $req['_priority']=0;
                $p1r[]=$req; $p1m[$k]=['sup'=>$sup,'brand'=>$sb,'article'=>$sa];
            }
            if ($req=$sup->buildSearchRequest('',$sa,false)) {
                $k=$code.':nobrand'; $req['_key']=$k; $req['_timeout']=$sup->getSearchTimeout(); $req['_priority']=1;
                $p1r[]=$req; $p1m[$k]=['sup'=>$sup,'brand'=>'','article'=>$sa,'noBrand'=>true];
            }
        }
        foreach ($suppliers as $sup) {
            if (!$sup->supportsCrossSearch()) continue;
            $code=$sup->getCode(); $sb=$bfs($sup,$targetEntry,$brand); $sa=$afs($sup,$targetEntry,$article);
            if ($req=$sup->buildSearchRequest($sb,$sa,true)) {
                $k=$code.':cross'; $req['_key']=$k; $req['_timeout']=min($sup->getSearchTimeout()+3,(int)$deadline); $req['_priority']=2;
                $p1r[]=$req; $p1m[$k]=['sup'=>$sup,'brand'=>$sb,'article'=>$sa,'isCross'=>true];
            }
        }

        $e1=new MultiCurlExecutor(); $r1=$e1->executeAll($p1r,$deadline * 0.5);
        $results=[]; $seen=[]; $analogMap = [];

        foreach ($r1 as $key=>$resp) {
            if (empty($resp['body'])) continue;
            $meta=$p1m[$key]??null; if(!$meta)continue;
            $sup=$meta['sup']; $src=$sup->getCode();
            try {
                $items=$sup->parseSearchResponse($resp['body'],$meta['brand'],$meta['article']);
                foreach ($items as $item) {
                    if (!($item instanceof SearchResultItem)) continue;
                    if ($item->price<=0 && $item->quantity<=0) continue;
                    $gk = BrandNormalizer::groupKey($item->brand, $item->article);
                    $itemNormArt = BrandNormalizer::normalizeArticle($item->article);
                    [$gkb] = array_pad(explode('|',$gk,2),2,'');
                    $gkbNorm = BrandNormalizer::normalize($gkb);
                    $isExact = ($itemNormArt === $normExactArt && $gkbNorm === $normExactBrand);
                    if (!$isExact) {
                        if (!isset($analogMap[$itemNormArt])) {
                            $analogMap[$itemNormArt] = ['brands'=>[$gk=>$item->brand],'articles'=>[$gk=>$item->article],'sources'=>[$src=>true],'example'=>$item];
                        } else {
                            $analogMap[$itemNormArt]['sources'][$src]=true;
                            if(!isset($analogMap[$itemNormArt]['brands'][$gk])) $analogMap[$itemNormArt]['brands'][$gk]=$item->brand;
                            $analogMap[$itemNormArt]['articles'][$gk]=$item->article;
                        }
                    }
                    $dk=$src.'|'.($item->stockId?:'').'|'.$item->price.'|'.($item->warehouse??'').'|'.$item->brand.'|'.$item->article;
                    if(isset($seen[$dk])) continue; $seen[$dk]=true;
                    $results[]=$item;
                }
            } catch (\Throwable $e) {}
        }
        $this->log("P1: results=".count($results)." analogArts=".count($analogMap));
        // Этап 7: добавляем brandMap в analogMap для Фазы 2
        foreach ($brandMap as $gk => $info) {
            [$gkBrand, $gkArt] = array_pad(explode('|', $gk, 2), 2, '');
            $na = BrandNormalizer::normalizeArticle($gkArt);
            $nb = BrandNormalizer::normalize($gkBrand);
            if (($nb === $normExactBrand && $na === $normExactArt) || $gk === $exactKey) continue;
            if (!isset($analogMap[$na])) {
                $analogMap[$na] = ['brands' => [], 'articles' => [], 'sources' => [], 'example' => null];
            }
            foreach (($info['sources'] ?? []) as $src) {
                $analogMap[$na]['sources'][$src] = true;
                $analogMap[$na]['brands'][$gk] = $info['brands'][$src] ?? $gkBrand;
                $analogMap[$na]['articles'][$gk] = $info['articles'][$src] ?? $gkArt;
            }
        }

        // ФАЗА 2
        uasort($analogMap, fn($a,$b)=>count($a['sources'])<=>count($b['sources']));
        $p2r=[]; $p2m=[];
        foreach ($analogMap as $normArt=>$am) {
            $missing=array_diff($allCodes,array_keys($am['sources']));
            if(empty($missing)) continue;
            $firstGk=array_key_first($am['brands']);
            $fb=$am['brands'][$firstGk]??$am['example']->brand;
            $fa=$am['articles'][$firstGk]??$am['example']->article;
            $bmFound=null;
            foreach($am['brands'] as $gk=>$b){if(isset($brandMap[$gk])){$bmFound=$brandMap[$gk];break;}}
            foreach($missing as $supCode){
                $sup=$this->factory->get($supCode); if(!$sup)continue;
                $tb=$fb; $ta=$fa;
                if($bmFound){$tb=!empty($bmFound['brands'][$supCode])?(string)$bmFound['brands'][$supCode]:$tb;$ta=!empty($bmFound['articles'][$supCode])?(string)$bmFound['articles'][$supCode]:$ta;}
                $req=$sup->buildSearchRequest($tb,$ta,false);
                if(!$req)$req=$sup->buildSearchRequest('',$fa,false);
                if(!$req)continue;
                $k2=$supCode.':fill:'.$normArt;
                $req['_key']=$k2; $req['_timeout']=4; $req['_priority']=3;
                $p2r[]=$req; $p2m[$k2]=['sup'=>$sup,'normArt'=>$normArt,'brand'=>$tb,'article'=>$ta];
            }
        }
        if(!empty($p2r)){
            $p2Deadline=max(10.0,$deadline*0.6);
            $this->log("P2: ".count($p2r)." for ".count(array_unique(array_column($p2m,'normArt')))." analogs");
            $e2=new MultiCurlExecutor(); $r2=$e2->executeAll($p2r,$p2Deadline); $added=0;
            foreach($r2 as $key=>$resp){
                if(empty($resp['body'])) continue;
                $meta=$p2m[$key]??null; if(!$meta)continue;
                $sup=$meta['sup']; $normArt=$meta['normArt']; $src=$sup->getCode();
                try{
                    $items=$sup->parseSearchResponse($resp['body'],$meta['brand'],$meta['article']);
                    foreach($items as $item){
                        if(!($item instanceof SearchResultItem)) continue;
                        if($item->price<=0&&$item->quantity<=0) continue;
                        if(BrandNormalizer::normalizeArticle($item->article)!==$normArt) continue;
                        $dk=$src.'|'.($item->stockId?:'').'|'.$item->price.'|'.($item->warehouse??'').'|'.$item->brand.'|'.$item->article;
                        if(isset($seen[$dk])) continue; $seen[$dk]=true;
                        $results[]=$item; $added++;
                    }
                } catch(\Throwable $e){}
            }
            $this->log("P2 done: +{$added}");
        }
        $this->log("TOTAL: ".count($results));
        usort($results,fn($a,$b)=>(!$a->isSched&&$b->isSched)?-1:(($a->isSched&&!$b->isSched)?1:$a->price<=>$b->price));
        return $results;
    }
    private function log(string $msg):void{@file_put_contents('/var/www/u3564357/data/www/liderws.ru/upload/logs/fullsearch_'.date('Y-m-d').'.log','['.date('H:i:s').'] '.$msg."\n",FILE_APPEND);}
}
