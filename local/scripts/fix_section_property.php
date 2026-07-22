<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("iblock");
$iblockId = 42;
$targetCodes = ["CML2_MANUFACTURER","TIP_3","KLASS_VYAZKOSTI_SAE","STANDART_API","STANDART_DOT","TIP_SHCHETKI","TSOKOL_LAMPY","SEZONNOST","TIP_DVIGATELYA","STORONA_KREPLENIYA","TIP_KREPLENIYA","INDEKS_DOPUSKA_VAG","TIP","TSVET"];
$DB->Query("UPDATE b_iblock_section_property SET SMART_FILTER=\"N\" WHERE IBLOCK_ID=$iblockId AND SECTION_ID=0");
foreach ($targetCodes as $c) {
  $res = CIBlockProperty::GetList([], ["IBLOCK_ID" => $iblockId, "CODE" => $c]);
  if ($prop = $res->GetNext()) {
    $pid = $prop["ID"];
    $check = $DB->Query("SELECT * FROM b_iblock_section_property WHERE IBLOCK_ID=$iblockId AND SECTION_ID=0 AND PROPERTY_ID=$pid");
    if ($check->Fetch()) {
      $DB->Query("UPDATE b_iblock_section_property SET SMART_FILTER=\"Y\" WHERE IBLOCK_ID=$iblockId AND SECTION_ID=0 AND PROPERTY_ID=$pid");
      echo "UPD: $c (ID:$pid)\n";
    } else {
      $DB->Query("INSERT INTO b_iblock_section_property (IBLOCK_ID, SECTION_ID, PROPERTY_ID, SMART_FILTER) VALUES ($iblockId, 0, $pid, \"Y\")");
      echo "INS: $c (ID:$pid)\n";
    }
  } else { echo "NF: $c\n"; }
}
echo "DONE\n";
