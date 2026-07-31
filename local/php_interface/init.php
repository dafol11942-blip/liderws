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

        // === ЭТАП 17: Москворечье (прайс-лист ✅) ===
        $factory->register(new \Lider\Supplier\MoskvorechieConnector([
            'API_KEY' => '2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf',
        ]));

        // === ОТКЛЮЧЕНЫ до добавления прайс-листов ===
        // $factory->register(new \Lider\Supplier\RosskoConnector([...]));
        // $factory->register(new \Lider\Supplier\BergConnector([...]));
        // $factory->register(new \Lider\Supplier\AutoeuroConnector([...]));
        // $factory->register(new \Lider\Supplier\PartKomConnector([...]));
        // $factory->register(new \Lider\Supplier\IxoraConnector([...]));
        // $factory->register(new \Lider\Supplier\TatpartsConnector([...]));
        // $factory->register(new \Lider\Supplier\AutorussConnector([...]));
        // $factory->register(new \Lider\Supplier\AutopiterConnector([...]));
    }
    return $factory;
}
