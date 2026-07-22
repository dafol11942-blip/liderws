<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');

$iblockId = 42;

$targetCodes = [
    'CML2_MANUFACTURER',
    'TIP_3',
    'KLASS_VYAZKOSTI_SAE',
    'STANDART_API',
    'STANDART_DOT',
    'TIP_SHCHETKI',
    'TSOKOL_LAMPY',
    'SEZONNOST',
    'TIP_DVIGATELYA',
    'STORONA_KREPLENIYA',
    'TIP_KREPLENIYA',
    'INDEKS_DOPUSKA_VAG',
    'TIP',
    'TSVET',
];

$ibp = new CIBlockProperty;

foreach ($targetCodes as $code) {
    $res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code]);
    if ($prop = $res->GetNext()) {
        $result = $ibp->Update($prop['ID'], ['SMART_FILTER' => 'Y']);
        echo $result ? "✓ {$code}\n" : "✗ {$code}: {$ibp->LAST_ERROR}\n";
    } else {
        echo "? {$code}: не найден\n";
    }
}

echo "\nГотово!\n";
