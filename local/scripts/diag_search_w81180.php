<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';
use Lider\Search\BrandNormalizer;

function line($s=''){echo $s."\n";}
function hr($t=''){line(str_repeat('=',70)); if($t){line($t); line(str_repeat('-',70));}}
function multiFetch(array $reqs, int $timeout=8): array {
    $out=[]; if(!$reqs)return $out; $mh=curl_multi_init(); $hs=[];
    foreach($reqs as $k=>$r){
        $ch=curl_init();
        curl_setopt_array($ch,[CURLOPT_URL=>$r['url'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$r['headers'],CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_ENCODING=>'']);
        if(($r['method']??'GET')==='POST'){curl_setopt($ch,CURLOPT_POST,true); if(!empty($r['body']))curl_setopt($ch,CURLOPT_POSTFIELDS,$r['body']);}
        curl_multi_add_handle($mh,$ch); $hs[$k]=$ch;
    }
    $rn=null; do{curl_multi_exec($mh,$rn); curl_multi_select($mh,0.1);}while($rn>0);
    foreach($hs as $k=>$ch){$out[$k]=['body'=>curl_multi_getcontent($ch),'http'=>curl_getinfo($ch,CURLINFO_HTTP_CODE)]; curl_multi_remove_handle($mh,$ch); curl_close($ch);} 
    curl_multi_close($mh); return $out;
}

$factory=getSupplierFactory();
$suppliers=$factory->allAvailable();
hr('SUPPLIERS');
foreach($suppliers as $s) line($s->getCode().' '.$s->getName());

hr('NORMALIZE');
foreach(['MANN-FILTER','Mann','LYNXauto','LYNX','lynxauto'] as $b) line("$b => map=".BrandNormalizer::map($b).' norm='.BrandNormalizer::normalize($b));
foreach(['W81180','W811/80','W 811/80','LC331','LC-331'] as $a) line("$a => ".BrandNormalizer::normalizeArticle($a));

$q='W81180';
hr("STAGE1 $q");
$breqs=[]; $bmeta=[];
foreach($suppliers as $s){$r=$s->buildBrandsRequest($q); if($r){$breqs[$s->getCode()]=$r; $bmeta[$s->getCode()]=$s;}}
$raw=[];
foreach(multiFetch($breqs) as $code=>$resp){
    line("[$code] HTTP {$resp['http']} len=".strlen((string)$resp['body']));
    if(empty($resp['body'])) continue;
    $brands=$bmeta[$code]->parseBrandsResponse($resp['body'],$q);
    foreach($brands as $br){
        $br['source']=$code; $raw[]=$br;
        $bn=BrandNormalizer::normalize($br['brand']); $an=BrandNormalizer::normalizeArticle((string)($br['article_nr'] ?? ''));
        if($bn===BrandNormalizer::normalize('MANN-FILTER') || $an==='w81180' || $bn===BrandNormalizer::normalize('LYNX') || $an==='lc331'){
            line("  {$br['brand']} | {$br['article_nr']} => $bn|$an");
        }
    }
}
$map=[];
foreach($raw as $br){
    $k=BrandNormalizer::groupKey($br['brand'],$br['article_nr']);
    if(!isset($map[$k])) $map[$k]=['brands'=>[],'articles'=>[],'article_nr'=>$br['article_nr'],'sources'=>[]];
    $map[$k]['brands'][$br['source']]=$br['brand'];
    $map[$k]['articles'][$br['source']]=$br['article_nr'];
    if(!in_array($br['source'],$map[$k]['sources'],true)) $map[$k]['sources'][]=$br['source'];
    $map[$k]['article_nr']=BrandNormalizer::pickDisplayArticle($map[$k]['articles'],$map[$k]['article_nr']);
}
$tk=BrandNormalizer::groupKey('MANN-FILTER','W81180');
hr("TARGET $tk");
if(!isset($map[$tk])){line('NOT IN MAP'); foreach($map as $k=>$i){if(strpos($k,'mann')!==false)line("alt $k sources=".implode(',',$i['sources']).' arts='.json_encode($i['articles'],JSON_UNESCAPED_UNICODE));}}
else {
    $e=$map[$tk];
    line('sources='.implode(',',$e['sources']));
    line('brands='.json_encode($e['brands'],JSON_UNESCAPED_UNICODE));
    line('articles='.json_encode($e['articles'],JSON_UNESCAPED_UNICODE));
    line('displayArt='.$e['article_nr'].' displayBrand='.BrandNormalizer::displayBrand(reset($e['brands'])));
}

hr('STAGE2 exact variants');
$variants=[];
if(isset($map[$tk])){
  foreach($map[$tk]['brands'] as $code=>$b){$variants[]=[$b,$map[$tk]['articles'][$code],'native-'.$code];}
  $variants[]=[BrandNormalizer::displayBrand('MANN-FILTER'),$map[$tk]['article_nr'],'canon-disp'];
}
$variants[]=['MANN-FILTER','W81180','plain'];
$variants[]=['MANN-FILTER','W811/80','slash'];
$sreqs=[];$smeta=[];$i=0;
foreach($suppliers as $sup){
  foreach($variants as $v){
    $req=$sup->buildSearchRequest($v[0],$v[1]); if(!$req)continue;
    $k=$sup->getCode().'#'.$i++; $sreqs[$k]=$req; $smeta[$k]=['code'=>$sup->getCode(),'sup'=>$sup,'b'=>$v[0],'a'=>$v[1],'t'=>$v[2]];
  }
}
foreach(multiFetch($sreqs) as $k=>$resp){
  $m=$smeta[$k]; $items=[];
  if($resp['http']==200 && $resp['body']){
    try{$items=$m['sup']->parseSearchResponse($resp['body'],$m['b'],$m['a']);}catch(Throwable $e){line($m['code'].' ERR '.$e->getMessage());}
  }
  $exact=0;$sample=[];
  foreach($items as $it){
    if(BrandNormalizer::normalize($it->brand)===BrandNormalizer::normalize('MANN-FILTER') && BrandNormalizer::normalizeArticle($it->article)==='w81180') $exact++;
    if(count($sample)<2)$sample[]="{$it->brand}|{$it->article}|{$it->price}";
  }
  if($exact>0 || $m['t']==='native-'.$m['code'] || str_starts_with($m['t'],'native'))
    line(sprintf('%-12s %-14s %-12s http=%s total=%d exact=%d %s %s',$m['code'],$m['b'],$m['a'],$resp['http'],count($items),$exact,$m['t'],$sample?implode(' || ',$sample):''));
}

// summary best per supplier
hr('BEST exact per supplier');
$best=[];
foreach($smeta as $k=>$m){/* filled below */}
foreach(multiFetch($sreqs) as $k=>$resp){/* already consumed - re-run lighter */}
// re-evaluate from fresh single best native only
foreach($suppliers as $sup){
  $code=$sup->getCode();
  $b=$map[$tk]['brands'][$code] ?? 'MANN-FILTER';
  $a=$map[$tk]['articles'][$code] ?? ($map[$tk]['article_nr'] ?? 'W81180');
  $req=$sup->buildSearchRequest($b,$a);
  if(!$req){line("$code NO REQ"); continue;}
  $resp=multiFetch(['x'=>$req]);
  $items=[];
  if($resp['x']['http']==200 && $resp['x']['body']) $items=$sup->parseSearchResponse($resp['x']['body'],$b,$a);
  $exact=0; foreach($items as $it){ if(BrandNormalizer::groupKey($it->brand,$it->article)===$tk || (BrandNormalizer::normalize($it->brand)===BrandNormalizer::normalize('MANN-FILTER') && BrandNormalizer::normalizeArticle($it->article)==='w81180')) $exact++; }
  line(sprintf('%-12s native [%s]/[%s] total=%d exact=%d',$code,$b,$a,count($items),$exact));
}

// LYNX from same brandmap or secondary
hr('LYNX LC331 in brandmap');
$lk=null; foreach($map as $k=>$i){ if(strpos($k,'lynx')!==false && strpos($k,'lc331')!==false){$lk=$k; break;}}
if(!$lk){
  // search brands LC331
  line('Not in W81180 map, stage1 LC331...');
  $breqs=[];$bmeta=[];$raw2=[];
  foreach($suppliers as $s){$r=$s->buildBrandsRequest('LC331'); if($r){$breqs[$s->getCode()]=$r;$bmeta[$s->getCode()]=$s;}}
  foreach(multiFetch($breqs) as $code=>$resp){
    if(empty($resp['body']))continue;
    foreach($bmeta[$code]->parseBrandsResponse($resp['body'],'LC331') as $br){
      $k=BrandNormalizer::groupKey($br['brand'],$br['article_nr']);
      if(strpos($k,'lynx')!==false){
        line("[$code] {$br['brand']} {$br['article_nr']} key=$k");
        if(!isset($map[$k]))$map[$k]=['brands'=>[],'articles'=>[],'article_nr'=>$br['article_nr'],'sources'=>[]];
        $map[$k]['brands'][$code]=$br['brand']; $map[$k]['articles'][$code]=$br['article_nr'];
        if(!in_array($code,$map[$k]['sources'],true))$map[$k]['sources'][]=$code;
        $lk=$k;
      }
    }
  }
}
if($lk){
  line("key=$lk sources=".implode(',',$map[$lk]['sources']));
  line(json_encode($map[$lk]['brands'],JSON_UNESCAPED_UNICODE).' '.json_encode($map[$lk]['articles'],JSON_UNESCAPED_UNICODE));
  foreach($suppliers as $sup){
    $code=$sup->getCode();
    $b=$map[$lk]['brands'][$code] ?? BrandNormalizer::displayBrand('LYNX');
    $a=$map[$lk]['articles'][$code] ?? 'LC331';
    $req=$sup->buildSearchRequest($b,$a); if(!$req){line("$code no req"); continue;}
    $resp=multiFetch(['x'=>$req]); $items=[];
    if($resp['x']['http']==200 && $resp['x']['body']) $items=$sup->parseSearchResponse($resp['x']['body'],$b,$a);
    $exact=0; foreach($items as $it){ if(BrandNormalizer::normalize($it->brand)===BrandNormalizer::normalize('LYNX') && BrandNormalizer::normalizeArticle($it->article)==='lc331') $exact++; }
    line(sprintf('%-12s [%s]/[%s] total=%d exactLYNX=%d',$code,$b,$a,count($items),$exact));
  }
}
hr('DONE');
