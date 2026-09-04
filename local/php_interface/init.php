<?php
require_once __DIR__ . '/lib/autoload.php';

AddEventHandler("catalog", "OnProductUpdate", "syncInStockProperty");
AddEventHandler("catalog", "OnProductAdd", "syncInStockProperty");
AddEventHandler("catalog", "OnProductSetAvailableUpdate", "syncInStockProperty");

// Быстрый путь для заказов "под удержанием оплаты" (см. план "Оплата в
// течение 15 минут", local/php_interface/order_create_handler.php): как
// только заказ помечается оплаченным — неважно, боевым платёжным модулем в
// админке или менеджером вручную — сразу пробуем отправить его поставщику,
// не дожидаясь ближайшего прохода cron/payment_hold_sweep.php (до 1 минуты).
// Событие OnSaleOrderPaid — давний, документированный (не D7) хук модуля
// sale, сработает независимо от того, какой платёжный обработчик реально
// стоит в админке. Если по какой-то причине событие не сработает — cron всё
// равно подхватит оплаченный заказ в течение минуты, это лишь ускоритель.
AddEventHandler("sale", "OnSaleOrderPaid", "dispatchHeldOrderOnPaymentEvent");

function dispatchHeldOrderOnPaymentEvent($orderId)
{
    try {
        $orderId = (int)$orderId;
        if (!$orderId) return;
        if (!CModule::IncludeModule('sale')) return;
        require_once __DIR__ . '/order_create_handler.php';
        if (function_exists('dispatchHeldOrderIfPaid')) {
            dispatchHeldOrderIfPaid($orderId);
        }
    } catch (\Throwable $e) {
        if (function_exists('logSupplierOrderDispatch')) {
            logSupplierOrderDispatch('OnSaleOrderPaid handler упал: ' . $e->getMessage());
        }
    }
}

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
            'API_KEY' => 'hRohAwdf9nEy:qb9WatcqtLCdxunJ6klPootnydulyYMZ',
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

        $factory->register(new \Lider\Supplier\AutorussConnector([
            'LOGIN' => 'Lider-16@bk.ru',
            'PASSWORD_MD5' => '00fd3781d2cfdf0d971b57fa7397cfac',
        ]));
        $factory->register(new \Lider\Supplier\AutopiterConnector([
            'USER_ID' => '165286',
            'PASSWORD' => 'LidGates16',
        ]));
    }
    return $factory;
}

/**
 * ID → название статуса (заказа и отгрузки — обе категории в одной таблице
 * b_sale_status). Не доверяем $arResult['INFO']['STATUS'] ядровых компонентов
 * sale.personal.order.* — он не подхватывает свежесозданные статусы (см.
 * список заказов/детали заказа), поэтому резолвим сами тем же джойном, каким
 * статусы уже сверялись через Adminer.
 */
function getOrderStatusNameMap(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            $db = \Bitrix\Main\Application::getConnection();
            // Не фильтруем по LID='ru' жёстко — статусы, заведённые вручную в
            // админке (SO/ST/SR/SX), у некоторых инсталляций сохраняются под
            // другим LID (не обязательно 'ru'), и тогда жёсткий JOIN ... AND
            // LID='ru' их молча теряет — сайт показывает сырой код статуса
            // вместо имени. Берём имя для LID='ru', если оно есть, иначе —
            // любое доступное (лучше показать что-то осмысленное, чем код).
            $rows = $db->query(
                "SELECT s.ID, sl.NAME, sl.LID FROM b_sale_status s
                 JOIN b_sale_status_lang sl ON sl.STATUS_ID = s.ID
                 ORDER BY (sl.LID = 'ru') DESC"
            )->fetchAll();
            foreach ($rows as $row) {
                if (!isset($map[$row['ID']])) {
                    $map[$row['ID']] = $row['NAME'];
                }
            }
        } catch (\Throwable $e) {
            // если БД недоступна — вызывающий код увидит сырой код статуса, не хуже прежнего
        }
    }
    return $map;
}

/**
 * Цвет для бейджа статуса ЗАКАЗА (b_sale_status.ID) — единая цветовая
 * индикация везде, где показывается статус (список заказов, детали заказа).
 * Неизвестный/ручной статус — нейтральный серый, а не ошибка: список статусов
 * в магазине может расширяться руками в админке, это ожидаемо.
 */
function getOrderStatusColor(string $statusId): string
{
    static $colors = [
        'N'  => 'blue',   // Принят, ожидается оплата
        'S'  => 'blue',   // Ожидает обработки
        'SO' => 'indigo', // Заказан у поставщика
        'ST' => 'purple', // Товар в пути от поставщика
        'SR' => 'green',  // Товар готов к выдаче
        'F'  => 'teal',   // Выполнен
        'SX' => 'red',    // Отказано поставщиком
        'DN' => 'gray',   // Ожидает обработки (отгрузка)
        'DF' => 'blue',   // Отгружен
    ];
    return $colors[$statusId] ?? 'gray';
}

/** Цвет и подпись для поставщико-независимого этапа позиции (см. PartKomConnector::normalizeStage()). */
function getSupplierStageColor(?string $stage): string
{
    static $colors = [
        'ordered'    => 'blue',
        'in_transit' => 'purple',
        'ready'      => 'green',
        'refused'    => 'red',
    ];
    return $colors[$stage] ?? 'gray';
}

function getSupplierStageLabel(?string $stage): string
{
    static $labels = [
        'ordered'    => 'Заказан у поставщика',
        'in_transit' => 'В пути',
        'ready'      => 'Готов к выдаче',
        'refused'    => 'Отказано',
    ];
    return $labels[$stage] ?? 'Статус не определён';
}
