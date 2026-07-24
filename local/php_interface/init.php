<?php
require_once __DIR__ . '/lib/autoload.php';

AddEventHandler("catalog", "OnProductUpdate", "syncInStockProperty");
AddEventHandler("catalog", "OnProductAdd", "syncInStockProperty");
AddEventHandler("catalog", "OnProductSetAvailableUpdate", "syncInStockProperty");

function syncInStockProperty($productId)
{
    if (!CModule::IncludeModule('iblock') || !CModule::IncludeModule('catalog')) return;
    $res = CIBlockElement::GetByID($productId);
    if (!$arElement = $res->GetNext()) return;
    $iblockId = $arElement['IBLOCK_ID'];
    $propCode = 'IN_STOCK';
    $dbProps = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propCode]);
    if (!$arProp = $dbProps->Fetch()) return;
    $totalAmount = 0;
    $dbStore = CCatalogStoreProduct::GetList([], ['PRODUCT_ID' => $productId], false, false, ['AMOUNT']);
    while ($arStore = $dbStore->Fetch()) $totalAmount += (int)$arStore['AMOUNT'];
    $isYes = $totalAmount > 0;
    if ($arProp['PROPERTY_TYPE'] === 'L') {
        $targetValue = $isYes ? 'Да' : 'Нет';
        $enumList = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propCode, 'VALUE' => $targetValue]);
        if ($arEnum = $enumList->GetNext()) $newValue = $arEnum['ID']; else $newValue = $targetValue;
    } else {
        $newValue = $isYes ? 'Да' : 'Нет';
    }
    CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [$propCode => $newValue]);
}

function getSupplierFactory(): \Lider\Supplier\SupplierFactory
{
    static $factory = null;
    if ($factory === null) {
        $factory = new \Lider\Supplier\SupplierFactory();

        $factory->register(new \Lider\Supplier\MoskvorechieConnector([
            'API_KEY' => '2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf',
        ]));

        $factory->register(new \Lider\Supplier\RosskoConnector([
            'KEY1' => 'd6907f0f857524815255b74cda86fe9b',
            'KEY2' => 'a514b4c11299686d7cfe8fd3563d1c58',
            'DELIVERY_ID' => '000000002',
            'ADDRESS_ID' => '71520',
        ]));

        $factory->register(new \Lider\Supplier\BergConnector([
            'API_KEY' => '9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36',
            'ADDRESS_ID' => 31173,
        ]));

        $factory->register(new \Lider\Supplier\AutoeuroConnector([
            'API_KEY' => 'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX',
            'DELIVERY_KEY' => 'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA',
        ]));

        $factory->register(new \Lider\Supplier\PartKomConnector([
            'LOGIN' => 'lider16',
            'PASSWORD' => 'LidGates16',
        ]));

        $factory->register(new \Lider\Supplier\IxoraConnector([
            'AUTH_CODE' => '460880B0988C8C204B2DD392EC81611D',
            'TIMEOUT' => 8,
        ]));

        $factory->register(new \Lider\Supplier\TatpartsConnector([
            'LOGIN' => 'lider-16@bk.ru',
            'PASSWORD' => "'8dTpDU8}Myr)*&",
            'TIMEOUT' => 10,
        ]));
    }
    return $factory;
}
