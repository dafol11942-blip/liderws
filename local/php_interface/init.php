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

// Свойство "Бренд" в iblock 42 (1c_catalog) заведено вручную, без CML2_-кода
// (в отличие от CML2_MANUFACTURER, который у части товаров пуст) — ищем его
// код по имени, а не хардкодим, т.к. в разных инфоблоках он может отличаться.
function getBrandPropertyCode(int $iblockId): string
{
    static $cache = [];
    if (array_key_exists($iblockId, $cache)) return $cache[$iblockId];
    $code = '';
    if (CModule::IncludeModule('iblock')) {
        $dbProps = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'NAME' => 'Бренд']);
        if ($arProp = $dbProps->Fetch()) $code = $arProp['CODE'];
    }
    return $cache[$iblockId] = $code;
}

// Запасной путь на случай, если PROPERTY_CODE компонента почему-то не подтянул
// getBrandPropertyCode() (например, кэш компонента собран до её резолва) —
// ищем свойство прямо в уже загруженных $item['PROPERTIES'] по имени "Бренд".
function resolveBrandFromProperties(array $properties): string
{
    foreach ($properties as $prop) {
        if (empty($prop['VALUE'])) continue;
        if (mb_strtolower(trim((string)($prop['NAME'] ?? ''))) !== 'бренд') continue;
        $value = is_array($prop['VALUE']) ? reset($prop['VALUE']) : $prop['VALUE'];
        return trim((string)$value);
    }
    return '';
}

// order_meta позиции корзины (см. SupplierOrderable::placeOrder()) хранится
// ЗДЕСЬ, а не в стандартном свойстве корзины SUPPLIER_ORDER_META — то свойство
// физически VARCHAR(255) (ядро Bitrix, b_sale_basket_props.VALUE, менять
// нельзя), а у некоторых поставщиков (АвтоЕвро — offer_key ~250 символов сам
// по себе) JSON туда не помещается и молча обрезается, ломая структуру при
// обратном чтении — заказ уходит без нужных данных, хотя в корзине всё верно.
// Таблица создаётся один раз вручную, см.
// local/php_interface/db/supplier_basket_order_meta_table.sql.
function saveSupplierBasketOrderMeta(int $basketItemId, array $orderMeta): void
{
    if ($basketItemId <= 0 || empty($orderMeta)) return;
    try {
        $db     = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();
        $json   = $helper->forSql(json_encode($orderMeta, JSON_UNESCAPED_UNICODE));
        $db->query(
            "INSERT INTO b_supplier_basket_order_meta (BASKET_ITEM_ID, ORDER_META_JSON) VALUES ({$basketItemId}, '{$json}')
             ON DUPLICATE KEY UPDATE ORDER_META_JSON = '{$json}'"
        );
    } catch (\Throwable $e) {
        // Не фатально: позиция всё равно добавится в корзину, просто не сможет
        // уйти автоматическим заказом у поставщика (dispatchSupplierOrders()
        // залогирует no_valid_items при отправке).
    }
}

function loadSupplierBasketOrderMeta(int $basketItemId): array
{
    if ($basketItemId <= 0) return [];
    try {
        $db  = \Bitrix\Main\Application::getConnection();
        $row = $db->query("SELECT ORDER_META_JSON FROM b_supplier_basket_order_meta WHERE BASKET_ITEM_ID = {$basketItemId}")->fetch();
        if (!$row) return [];
        $decoded = json_decode((string)$row['ORDER_META_JSON'], true);
        return is_array($decoded) ? $decoded : [];
    } catch (\Throwable $e) {
        return [];
    }
}

// Срок актуальности заказной позиции (цена/остаток у поставщика) — общий и для
// корзины (SUPPLIER_ADDED_AT в свойствах, см. sale.basket.basket/lider_style/template.php),
// и для избранного (CONFIRMED_AT в b_user_favorites, см. local/ajax/favorites.php).
if (!defined('CART_TTL_SECONDS')) define('CART_TTL_SECONDS', 2 * 3600);

// Избранное (см. local/php_interface/db/user_favorites_table.sql, local/ajax/favorites.php).
function getFavoritesCount(int $userId): int
{
    if ($userId <= 0) return 0;
    try {
        $db  = \Bitrix\Main\Application::getConnection();
        $row = $db->query("SELECT COUNT(*) AS CNT FROM b_user_favorites WHERE USER_ID = {$userId}")->fetch();
        return (int)($row['CNT'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

// Для одним запросом проставить "активное" сердечко у карточек каталога/страницы товара.
function getFavoritedCatalogIds(int $userId, array $productIds): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if ($userId <= 0 || empty($productIds)) return [];
    try {
        $db   = \Bitrix\Main\Application::getConnection();
        $ids  = implode(',', $productIds);
        $rows = $db->query("SELECT PRODUCT_ID FROM b_user_favorites WHERE USER_ID = {$userId} AND TYPE = 'catalog' AND PRODUCT_ID IN ({$ids})")->fetchAll();
        return array_map(function ($r) { return (int)$r['PRODUCT_ID']; }, $rows);
    } catch (\Throwable $e) {
        return [];
    }
}

// Плоский список "brand|article|supplier" для гидратации состояния сердечек в search/index.php
// на клиенте — карточки офферов там рисует JS из JSON, а не PHP-цикл.
function getFavoritedSupplierKeys(int $userId): array
{
    if ($userId <= 0) return [];
    try {
        $db   = \Bitrix\Main\Application::getConnection();
        $rows = $db->query("SELECT ARTICLE, BRAND, SUPPLIER FROM b_user_favorites WHERE USER_ID = {$userId} AND TYPE = 'supplier'")->fetchAll();
        return array_map(function ($r) {
            return ($r['BRAND'] ?? '') . '|' . ($r['ARTICLE'] ?? '') . '|' . ($r['SUPPLIER'] ?? '');
        }, $rows);
    } catch (\Throwable $e) {
        return [];
    }
}

function getYandexMapsApiKey(): string
{
    static $key = null;
    if ($key === null) {
        $configFile = __DIR__ . '/config/yandex_maps_config.php';
        $config = is_file($configFile) ? require $configFile : [];
        $key = (string)($config['API_KEY'] ?? '');
    }
    return $key;
}

function getMobileIdClient(): \Lider\Auth\MobileIdClient
{
    static $client = null;
    if ($client === null) {
        $configFile = __DIR__ . '/config/mobileid_config.php';
        $config = is_file($configFile) ? require $configFile : [];
        $client = new \Lider\Auth\MobileIdClient(
            (string)($config['CLIENT_ID'] ?? ''),
            (string)($config['API_SECRET'] ?? '')
        );
    }
    return $client;
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
            'PAYMENT_ID' => 1,
            'REQUISITE_ID' => 20534,
            'CONTACT_NAME' => 'Сергей Викторович',
            'CONTACT_PHONE' => '+7(917)223-61-24',
        ]));

        $factory->register(new \Lider\Supplier\BergConnector([
            'API_KEY' => '9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36',
            'ADDRESS_ID' => 31173,
        ]));

        $factory->register(new \Lider\Supplier\AutoeuroConnector([
            'API_KEY' => 'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX',
            // Доставка. Респ Татарстан, г Елабуга, пр-кт Нефтяников, д 4 (склад по умолчанию)
            'DELIVERY_KEY' => 'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA',
            // Один аккаунт, второй адрес доставки — найден через /get_deliveries
            // ("Доставка. шнв|Респ Татарстан, г Елабуга, ул Баки Урманче, д 17А").
            // Код склада 'baki_urmanche' проставляется в order_create_handler.php
            // (resolveOrderWarehouseCode()) по выбранному в форме адресу самовывоза.
            'DELIVERY_KEYS_BY_WAREHOUSE' => [
                'baki_urmanche' => 'oQF5DLBhmuS097rxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA',
            ],
            // "Винокуров С.В. ИП (Елабуга)" — без префикса "ННН" у второго
            // плательщика в /get_payers (тот, судя по имени, дефолтный/неверно
            // заполненный, см. обсуждение при подключении оформления заказа).
            'PAYER_KEY' => '0Kc69cV474yHHTE31YsB5LoW6x1sbxW0Bt9mQklg1wakK5Ow21hA',
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
            'PAYMENT_METHOD' => 1062,
            'SHIPMENT_ADDRESS' => 1696765,
        ]));
        $factory->register(new \Lider\Supplier\AutopiterConnector([
            'USER_ID' => '165286',
            'PASSWORD' => 'LidGates16',
        ]));
        $factory->register(new \Lider\Supplier\ArmtekConnector([
            'LOGIN' => 'lider1-16@bk.ru',
            'PASSWORD' => 'LidGates166',
            // VKORG=4220 (со слов поставщика) и KUNRG=40039944 были неверны —
            // Армтек отвечал "Пользователь не настроен. Не установлена
            // сбытовая организация" / "Покупатель не может быть установлен
            // для данного пользователя [40039944]". Проверено напрямую через
            // getUserVkorgList (VKORG=4000) и getUserInfo/STRUCTURE=1 (KUNRG —
            // это RG_TAB[0].KUNNR, а не KUNAG верхнего уровня, которым
            // ошибочно был заполнен KUNRG).
            'VKORG' => '4000',
            'KUNRG' => '43039417',
            'KUNWE' => '43039417',
            'KUNZA' => '48022996',
            'VBELN' => '40359920',
        ]));
        $factory->register(new \Lider\Supplier\ShateMConnector([
            'API_KEY' => 'aa290d6a-2e79-4f2c-858e-c9cf5c9899f3',
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
