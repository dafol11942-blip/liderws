<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Только POST']));
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/OfferTokenStore.php');
use Lider\Search\OfferTokenStore;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$article      = trim($data['article'] ?? '');
$brand        = trim($data['brand'] ?? '');
$supplier     = trim($data['supplier'] ?? 'moskvorechie');
$quantity     = max(1, (int)($data['quantity'] ?? 1));
$taskId       = trim($data['task'] ?? '');
$offerToken   = trim($data['offer_token'] ?? '');

if (empty($article)) {
    die(json_encode(['success' => false, 'message' => 'Не указан артикул']));
}

// Токен предложения — единственный надёжный источник настоящего названия склада
// (клиенту оно не передаётся, если он не из группы «Менеджер», см. search/ajax.php).
// Также используется, чтобы не дать заказать больше, чем реально было в наличии
// на момент поиска.
$resolvedOffer = ($taskId && $offerToken) ? OfferTokenStore::resolve($taskId, $offerToken) : null;
if ($resolvedOffer) {
    $supplier = $resolvedOffer['supplier'] ?: $supplier;
    $quantity = min($quantity, max(1, (int)$resolvedOffer['quantity']));
} else {
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_error.log',
        '[' . date('Y-m-d H:i:s') . '] offer_token не найден (task=' . $taskId . '), склад не будет сохранён' . "\n",
        FILE_APPEND
    );
}

try {
    CModule::IncludeModule('sale');
    CModule::IncludeModule('catalog');

    $factory   = getSupplierFactory();
    $connector = $factory->get($supplier);
    if (!$connector) die(json_encode(['success' => false, 'message' => 'Поставщик не найден']));

    // Быстрый путь: все данные (цена/остаток/склад/срок/название) уже были получены
    // и сохранены в OfferTokenStore во время поиска — их достаточно, повторно дёргать
    // API поставщика не нужно. getDetail() — синхронный сетевой запрос с таймаутом
    // до 8-10с; вызывать его на КАЖДОЕ добавление в корзину держит PHP-воркер и
    // соединение с БД занятыми всё это время — при медленном/недоступном поставщике
    // это била по всему сайту разом (см. зависания каталога/картинок при нагрузке).
    // Поэтому обращаемся к поставщику напрямую, только если токен не найден/истёк
    // или в нём почему-то нет цены — то есть в редком деградированном случае.
    $itemName      = (string)($resolvedOffer['name'] ?? '');
    $basePrice     = (float)($resolvedOffer['price'] ?? 0);
    $realWarehouse = (string)($resolvedOffer['warehouse'] ?? '');
    $deliveryDays  = $resolvedOffer['delivery_days'] ?? null;
    $deliveryLabel = $resolvedOffer['delivery_label'] ?? null;
    $deliveryTime  = $resolvedOffer['delivery_time'] ?? null;
    $qtyAvail      = (int)($resolvedOffer['quantity'] ?? 0) ?: $quantity;
    // Непрозрачный пакет служебных ID для оформления заказа у поставщика
    // (см. SupplierOrderable) — корзина его только хранит и переносит дальше.
    $orderMeta     = $resolvedOffer['order_meta'] ?? [];

    // Отдельно от имени/цены — order_meta мог не долететь и тогда, когда токен
    // в целом был найден (напр. позиция лежит в корзине ещё с прошлой версии кода,
    // до того как order_from_supplier.php начал сохранять SUPPLIER_ORDER_META).
    // Без него заказ у поставщика впоследствии не соберётся (см. placeOrder()),
    // поэтому довосстанавливаем его тем же getDetail(), даже если имя/цена и так
    // были известны.
    if ($itemName === '' || $basePrice <= 0 || empty($orderMeta)) {
        $item = $connector->getDetail($article, $brand);

        if ($item) {
            if ($itemName === '')  $itemName  = $item->name;
            if ($basePrice <= 0)   $basePrice = $item->price;
            $realWarehouse = $realWarehouse !== '' ? $realWarehouse : (string)($item->warehouse ?? '');
            $deliveryDays  = $deliveryDays  ?? ($item->deliveryDays ?? -1);
            $deliveryLabel = $deliveryLabel ?? $item->deliveryLabel;
            $deliveryTime  = $deliveryTime  ?? $item->deliveryTimeLabel;
            $qtyAvail      = $qtyAvail ?: ($item->quantity ?? $quantity);
            if (empty($orderMeta)) $orderMeta = $item->orderMeta ?? [];
        } elseif ($itemName === '' || $basePrice <= 0) {
            // Без имени/цены товар в корзину не добавить — это по-прежнему фатально.
            die(json_encode(['success' => false, 'message' => 'Товар не найден']));
        }
        // Если $item не найден, но имя/цена уже были — просто остаёмся без
        // order_meta (позиция добавится в корзину, но пока не сможет уйти
        // автоматическим заказом у поставщика).
    }
    $deliveryDays = $deliveryDays ?? -1;
    $orderMetaJson = json_encode($orderMeta, JSON_UNESCAPED_UNICODE);

    require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');
    $price = getDisplayPrice($basePrice);

    // Служебный товар
    $xmlId = 'SUPPLIER_ORDER_' . $supplier;
    $exist = CIBlockElement::GetList([], ['IBLOCK_ID' => 42, 'XML_ID' => $xmlId, 'ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['ID']);
    $productId = ($el = $exist->Fetch()) ? $el['ID'] : 0;

    if (!$productId) {
        $el = new CIBlockElement;
        $productId = $el->Add([
            'IBLOCK_ID' => 42, 'NAME' => 'Заказная позиция (' . $connector->getName() . ')',
            'XML_ID' => $xmlId, 'ACTIVE' => 'Y',
        ]);
    }
    if (!$productId) die(json_encode(['success' => false, 'message' => 'Ошибка создания товара']));

    // Добавляем в корзину
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );

    // Читает значение свойства позиции по CODE. BasketPropertiesCollection не имеет
    // метода getItemValues() (это не существующий в D7 API метод — вопреки тому, что
    // им уже пользовался старый код ниже; единственный документированный способ —
    // ручной перебор коллекции через getField()).
    $readProp = function ($props, string $code) {
        foreach ($props as $p) {
            if ($p->getField('CODE') === $code) return $p->getField('VALUE');
        }
        return null;
    };

    $existItem = null;
    foreach ($basket as $basketItem) {
        if ($basketItem->getProductId() != $productId) continue;
        $bProps = $basketItem->getPropertyCollection();
        if ($readProp($bProps, 'SUPPLIER_ARTICLE') === $article && $readProp($bProps, 'SUPPLIER_BRAND') === $brand) {
            $existItem = $basketItem;
            break;
        }
    }

    // Создаёт свойство, если его ещё нет, иначе обновляет значение существующего.
    $upsertProp = function ($props, string $code, string $name, $value) {
        foreach ($props as $p) {
            if ($p->getField('CODE') === $code) {
                $p->setField('VALUE', $value);
                return;
            }
        }
        $p = $props->createItem();
        $p->setFields(['NAME' => $name, 'CODE' => $code, 'VALUE' => $value]);
    };

    if ($existItem) {
        $existItem->setField('QUANTITY', $existItem->getQuantity() + $quantity);
        // Мы только что получили свежие данные от поставщика — обновляем TTL и срок
        // доставки/остаток позиции, чтобы повторное добавление считалось подтверждением
        // актуальности (см. TTL корзины 2ч в /cart/).
        $props = $existItem->getPropertyCollection();
        $upsertProp($props, 'SUPPLIER_DELIVERY_DAYS',  'Срок доставки (дн)', $deliveryDays);
        $upsertProp($props, 'SUPPLIER_DELIVERY_LABEL', 'Срок доставки',      (string)$deliveryLabel);
        $upsertProp($props, 'SUPPLIER_DELIVERY_TIME',  'Время доставки',     (string)$deliveryTime);
        $upsertProp($props, 'SUPPLIER_QTY_AVAIL',      'Остаток у поставщика', $qtyAvail);
        $upsertProp($props, 'SUPPLIER_ADDED_AT',       'Подтверждено',       (string)time());
        $upsertProp($props, 'SUPPLIER_ORDER_META',     'Данные для заказа',  $orderMetaJson);
    } else {
        $basketItem = $basket->createItem('catalog', $productId);
        $basketItem->setFields([
            'QUANTITY' => $quantity, 'CURRENCY' => 'RUB',
            'LID' => \Bitrix\Main\Context::getCurrent()->getSite(),
            'PRICE' => $price, 'CUSTOM_PRICE' => 'Y', 'NAME' => $itemName,
        ]);

        $props = $basketItem->getPropertyCollection();
        $list = [
            ['SUPPLIER_ARTICLE',       'Артикул',            $article],
            ['SUPPLIER_BRAND',         'Бренд',              $brand],
            ['SUPPLIER_NAME',          'Поставщик',          $supplier],
            ['SUPPLIER_WAREHOUSE',     'Склад',              $realWarehouse],
            ['SUPPLIER_TITLE',         'Название',           $itemName],
            ['SUPPLIER_PRICE_BASE',    'Закупочная цена',    $basePrice],
            ['SUPPLIER_DELIVERY_DAYS', 'Срок доставки (дн)', $deliveryDays],
            ['SUPPLIER_DELIVERY_LABEL','Срок доставки',      (string)$deliveryLabel],
            ['SUPPLIER_DELIVERY_TIME', 'Время доставки',     (string)$deliveryTime],
            ['SUPPLIER_QTY_AVAIL',     'Остаток у поставщика', $qtyAvail],
            ['SUPPLIER_ADDED_AT',      'Подтверждено',       (string)time()],
            ['SUPPLIER_ORDER_META',    'Данные для заказа',  $orderMetaJson],
        ];
        foreach ($list as [$code, $name, $value]) {
            $p = $props->createItem();
            $p->setFields(['NAME' => $name, 'CODE' => $code, 'VALUE' => $value]);
        }
    }

    $basket->save();

    $cartQty = 0;
    foreach ($basket as $bi) {
        $cartQty += (int)$bi->getQuantity();
    }
    $_SESSION['CART_QTY'] = $cartQty; // держим счётчик в шапке (header.php) без запроса к БД

    echo json_encode(['success' => true, 'message' => 'Добавлено в корзину', 'cart_url' => '/personal/cart/', 'cart_qty' => $cartQty]);

} catch (\Throwable $e) {
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_error.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n", FILE_APPEND
    );
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
