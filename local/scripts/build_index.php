<?php
$_SERVER["DOCUMENT_ROOT"]="/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("iblock");
$indexer = \Bitrix\Iblock\PropertyIndex\Manager::createIndexer(42);
$indexer->startIndex();
$total=0;
$step=0;
do {
    $cnt=$indexer->continueIndex();
    $total+=$cnt;
    $step++;
    if($step%5==0||$cnt>0) echo "Step $step: +$cnt (total $total)\n";
} while($cnt>0);
$indexer->endIndex();
echo "Done! Total: $total\n";
$r=$DB->Query("SELECT COUNT(*) as cnt FROM b_iblock_42_index");
$row=$r->Fetch();
echo "Index rows: {$row["cnt"]}\n";
