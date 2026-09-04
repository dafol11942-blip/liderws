<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Полностью свой рендер деталей заказа — раньше делегировал в ядровой
 * bitrix:sale.personal.order.detail (.default), который живёт вне репозитория
 * и требовал SSH-археологии на каждое изменение (см. план "Свои шаблоны для
 * истории заказов и деталей заказа"). Теперь — собственные запросы к Sale API,
 * по тому же принципу, что уже сделано для /cart/ (sale.basket.basket/lider_style)
 * и /order/ (sale.order.ajax/lider_style).
 *
 * $arResult здесь — результат ВНЕШНЕГО компонента-роутера bitrix:sale.personal.order
 * (не ядрового sale.personal.order.detail, от которого мы отказались) — из него
 * берём только ID заказа и ссылку "к списку", оба поля этот роутер строит сам.
 */

CModule::IncludeModule('sale');
CModule::IncludeModule('iblock');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

$isMgr = isManager();
$pathToList = $arResult["PATH_TO_LIST"] ?? '/personal/orders/';

$orderId = (int)($arResult["VARIABLES"]["ID"] ?? 0);
$order = $orderId ? \Bitrix\Sale\Order::load($orderId) : null;

// Доступ: свой заказ или менеджер. Раньше это проверял ядровой компонент —
// теперь проверяем сами.
if (!$order || (!$isMgr && (int)$order->getField('USER_ID') !== (int)$USER->GetID())) {
    ?>
    <div class="order-detail-page">
        <div class="cart-empty">
            <h2>Заказ не найден</h2>
            <p>Возможно, он был удалён, либо у вас нет доступа к нему.</p>
            <a href="<?= htmlspecialchars($pathToList) ?>" class="btn btn--primary">К списку заказов</a>
        </div>
    </div>
    <style>
        .cart-empty { text-align: center; padding: 80px 20px; }
        .cart-empty h2 { font-size: 18px; font-weight: 700; margin-bottom: 6px; color: var(--black); }
        .cart-empty p { color: var(--gray); margin-bottom: 20px; font-size: 14px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; border-radius: var(--radius); cursor: pointer; text-decoration: none; border: 1px solid transparent; font-family: var(--font); font-size: 14px; }
        .btn--primary { background: var(--blue); color: #fff; border-color: var(--blue); padding: 14px 24px; }
    </style>
    <?php
    return;
}

$statusMap = getOrderStatusNameMap();
$statusName = $statusMap[$order->getField('STATUS_ID')] ?? $order->getField('STATUS_ID');

// BasketPropertiesCollection не имеет метода getItemValues() — читаем свойство
// по CODE вручную (тот же приём, что в order_from_supplier.php/order_create_handler.php).
$readProp = function ($props, string $code) {
    foreach ($props as $p) {
        if ($p->getField('CODE') === $code) return $p->getField('VALUE');
    }
    return null;
};

$fetchLiveStatus = function (int $basketItemId) use ($orderId): ?array {
    if (!$orderId || !$basketItemId) return null;
    try {
        $db = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();
        $reference = $orderId . '_' . $basketItemId;
        $row = $db->query(
            "SELECT STATE_TEXT, STAGE, LAST_CHECKED_AT FROM b_supplier_order_item
             WHERE REFERENCE = '" . $helper->forSql($reference) . "' ORDER BY ID DESC LIMIT 1"
        )->fetch();
        return $row ?: null;
    } catch (\Throwable $e) {
        return null;
    }
};

// ── Позиции ────────────────────────────────────────────
$items = [];
$totalQty = 0;
$basket = $order->getBasket();
if ($basket) {
    foreach ($basket as $basketItem) {
        $props = $basketItem->getPropertyCollection();
        $article = (string)($readProp($props, 'SUPPLIER_ARTICLE') ?? '');
        $brand = (string)($readProp($props, 'SUPPLIER_BRAND') ?? '');
        $supplierCode = (string)($readProp($props, 'SUPPLIER_NAME') ?? '');
        $productId = (int)$basketItem->getProductId();

        // Товар со своего склада — своих артикула/бренда в свойствах нет,
        // берём с элемента каталога (тот же приём, что в /cart/).
        if ($article === '' && $productId > 0) {
            $artRes = CIBlockElement::GetProperty(42, $productId, [], ['CODE' => 'CML2_ARTICLE']);
            if ($row = $artRes->Fetch()) $article = (string)($row['VALUE'] ?? '');
        }
        if ($brand === '' && $productId > 0) {
            $brandRes = CIBlockElement::GetProperty(42, $productId, [], ['CODE' => 'CML2_MANUFACTURER']);
            if ($row = $brandRes->Fetch()) $brand = (string)($row['VALUE_ENUM'] ?? $row['VALUE'] ?? '');
        }

        $deliveryLabel = $readProp($props, 'SUPPLIER_DELIVERY_LABEL');
        $deliveryTime  = $readProp($props, 'SUPPLIER_DELIVERY_TIME');
        $deliveryDays  = $readProp($props, 'SUPPLIER_DELIVERY_DAYS');
        $deliveryText  = '';
        if ($deliveryLabel) {
            $deliveryText = $deliveryLabel . ($deliveryTime ? ' ' . $deliveryTime : '');
        } elseif ($deliveryDays !== null && (int)$deliveryDays >= 0) {
            $deliveryText = $deliveryDays . ' дн.';
        }

        $img = SITE_TEMPLATE_PATH . '/assets/images/no-photo.png';
        if ($productId > 0) {
            $el = CIBlockElement::GetByID($productId)->GetNextElement();
            if ($el) {
                $fields = $el->GetFields();
                $preview = $fields['PREVIEW_PICTURE'] ?? $fields['DETAIL_PICTURE'];
                if ($preview) {
                    $imgPath = CFile::GetPath($preview);
                    if ($imgPath) $img = $imgPath;
                }
            }
        }

        $liveStatus = ($isMgr && $supplierCode !== '') ? $fetchLiveStatus($basketItem->getId()) : null;

        $supplierLabel = $supplierCode;
        if ($supplierCode !== '' && function_exists('getSupplierFactory')) {
            $conn = getSupplierFactory()->get($supplierCode);
            if ($conn) $supplierLabel = $conn->getName();
        }

        $qty = (int)$basketItem->getQuantity();
        $price = (float)$basketItem->getPrice();

        $items[] = [
            'name'          => (string)$basketItem->getField('NAME'),
            'url'           => $productId ? '/catalog/' . $productId . '/' : '#',
            'img'           => $img,
            'article'       => $article,
            'brand'         => $brand,
            'supplier_code' => $supplierCode,
            'supplier_label'=> $supplierLabel,
            'warehouse'     => (string)($readProp($props, 'SUPPLIER_WAREHOUSE') ?? ''),
            'price_base'    => (float)($readProp($props, 'SUPPLIER_PRICE_BASE') ?? 0),
            'delivery_text' => $deliveryText,
            'live_status'   => $liveStatus,
            'qty'           => $qty,
            'price'         => $price,
            'sum'           => $price * $qty,
        ];
        $totalQty += $qty;
    }
}

// ── Отгрузка ───────────────────────────────────────────
$shipments = [];
foreach ($order->getShipmentCollection() as $shipment) {
    if ($shipment->isSystem()) continue;
    $shipments[] = [
        'name'         => (string)$shipment->getField('DELIVERY_NAME'),
        'status_name'  => $statusMap[$shipment->getField('STATUS_ID')] ?? $shipment->getField('STATUS_ID'),
        'status_color' => getOrderStatusColor($shipment->getField('STATUS_ID')),
        'price'        => (float)$shipment->getPrice(),
    ];
}

// ── Оплата ─────────────────────────────────────────────
$payments = [];
foreach ($order->getPaymentCollection() as $payment) {
    $paySystemName = '';
    try {
        $service = \Bitrix\Sale\PaySystem\Manager::getObjectById($payment->getPaymentSystemId());
        if ($service) $paySystemName = $service->getField('NAME');
    } catch (\Throwable $e) {}
    $payments[] = [
        'name' => $paySystemName ?: 'Не выбран',
        'paid' => $payment->isPaid(),
    ];
}

// ── Свойства заказа (ФИО/телефон/адрес и т.д.) ────────
$orderProps = [];
foreach ($order->getPropertyCollection() as $property) {
    $value = $property->getValue();
    if (is_array($value)) $value = implode(', ', $value);
    if ((string)$value === '') continue;
    $orderProps[] = [
        'name'  => $property->getField('NAME'),
        'value' => (string)$value,
    ];
}

$totalFmt = number_format((float)$order->getPrice(), 0, ',', ' ') . ' ₽';

$statusColor = getOrderStatusColor($order->getField('STATUS_ID'));
$isRefused = $order->getField('STATUS_ID') === 'SX';

$dateInsert = $order->getField('DATE_INSERT');
if ($dateInsert instanceof \Bitrix\Main\Type\DateTime) {
    $dateFmt = $dateInsert->format('d.m.Y H:i');
} else {
    $dateFmt = (string)($dateInsert ?? '');
}
?>

<div class="order-detail-page">
    <div class="order-detail-head">
        <div>
            <h1 class="order-detail-title">Заказ №<?= htmlspecialchars($order->getField('ACCOUNT_NUMBER')) ?></h1>
            <div class="order-detail-date"><?= htmlspecialchars($dateFmt) ?></div>
        </div>
        <div class="status-pill status-pill--<?= $statusColor ?> order-detail-status"><?= htmlspecialchars($statusName) ?></div>
    </div>
    <a href="<?= htmlspecialchars($pathToList) ?>" class="order-detail-back">← К списку заказов</a>

    <?php if ($isRefused): ?>
    <div class="status-banner status-banner--refused" style="margin-bottom: 20px;">
        <span class="status-banner__icon">⚠</span>
        <span>Заказ отменён — товар недоступен у поставщика (снят пользователем/поставщиком). Мы свяжемся с вами для уточнения деталей.</span>
    </div>
    <?php endif; ?>

    <div class="order-detail-layout">
        <div class="order-detail-items">
            <?php foreach ($items as $item): ?>
            <div class="cart-item">
                <div class="cart-item__img">
                    <a href="<?= htmlspecialchars($item['url']) ?>">
                        <img src="<?= htmlspecialchars($item['img']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="cart-item__info">
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="cart-item__name"><?= htmlspecialchars($item['name']) ?></a>
                    <?php if ($item['article'] !== '' || $item['brand'] !== ''): ?>
                    <div class="cart-item__article">
                        <?php if ($item['brand'] !== ''): ?>Бренд: <b><?= htmlspecialchars($item['brand']) ?></b><?php if ($item['article'] !== ''): ?> · <?php endif; endif; ?>
                        <?php if ($item['article'] !== ''): ?>Артикул: <b><?= htmlspecialchars($item['article']) ?></b><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="cart-item__price-unit"><?= number_format($item['price'], 0, ',', ' ') ?> ₽ / шт.</div>
                    <?php if ($item['supplier_code'] !== ''): ?>
                    <div class="cart-item__meta">
                        <?php if ($isMgr): ?>
                            Поставщик: <?= htmlspecialchars($item['supplier_label']) ?><?php if ($item['warehouse'] !== ''): ?> · Склад: <?= htmlspecialchars($item['warehouse']) ?><?php endif; ?><?php if ($item['delivery_text'] !== ''): ?> · Доставка: <?= htmlspecialchars($item['delivery_text']) ?><?php endif; ?><?php if ($item['price_base'] > 0): ?> · Закупка: <?= number_format($item['price_base'], 0, ',', ' ') ?> ₽<?php endif; ?>
                        <?php elseif ($item['delivery_text'] !== ''): ?>
                            Доставка: <?= htmlspecialchars($item['delivery_text']) ?>
                        <?php endif; ?>
                    </div>
                    <?php $liveStatusArr = $item['live_status'] ?? []; ?>
                    <?php if ($isMgr): ?>
                    <div class="cart-item__meta cart-item__meta--status">
                        Статус у поставщика:
                        <span class="status-pill status-pill--<?= getSupplierStageColor($liveStatusArr['STAGE'] ?? null) ?>">
                            <?= ($liveStatusArr['STATE_TEXT'] ?? '') !== '' ? htmlspecialchars($liveStatusArr['STATE_TEXT']) : 'ещё не проверялся' ?>
                        </span>
                        <?php if (!empty($liveStatusArr['LAST_CHECKED_AT'])): ?>
                            <span class="cart-item__meta-time">(<?= htmlspecialchars($liveStatusArr['LAST_CHECKED_AT']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="cart-item__price">
                    <div class="cart-item__qty-label"><?= $item['qty'] ?> шт.</div>
                    <div class="cart-item__sum"><?= number_format($item['sum'], 0, ',', ' ') ?> ₽</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="order-detail-sidebar">
            <div class="order-detail-summary">
                <h3 class="order-detail-summary__title">Информация о заказе</h3>
                <div class="order-detail-summary__rows">
                    <div class="order-detail-summary__row">
                        <span>Товары (<?= $totalQty ?> шт.)</span>
                        <span><?= $totalFmt ?></span>
                    </div>
                    <?php foreach ($shipments as $shipment): ?>
                    <div class="order-detail-summary__row">
                        <span>Доставка</span>
                        <span><?= htmlspecialchars($shipment['name']) ?></span>
                    </div>
                    <div class="order-detail-summary__row">
                        <span>Статус доставки</span>
                        <span><span class="status-pill status-pill--<?= $shipment['status_color'] ?>"><?= htmlspecialchars($shipment['status_name']) ?></span></span>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($payments as $payment): ?>
                    <div class="order-detail-summary__row">
                        <span>Оплата</span>
                        <span><?= htmlspecialchars($payment['name']) ?> — <?= $payment['paid'] ? 'оплачено' : 'не оплачено' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="order-detail-summary__total">
                    <span>Итого</span>
                    <span><?= $totalFmt ?></span>
                </div>

                <?php if (!empty($orderProps)): ?>
                <h3 class="order-detail-summary__title">Контактные данные</h3>
                <ul class="order-detail-props">
                    <?php foreach ($orderProps as $prop): ?>
                    <li><span class="order-detail-props__name"><?= htmlspecialchars($prop['name']) ?>:</span> <?= htmlspecialchars($prop['value']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.order-detail-page { max-width: 1240px; margin: 0 auto; padding: 30px 20px; font-family: var(--font); }
.order-detail-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 10px; }
.order-detail-title { font-size: 26px; font-weight: 800; color: var(--black); margin: 0; }
.order-detail-date { font-size: 13px; color: var(--gray); margin-top: 4px; }
.order-detail-status { font-size: 13px; padding: 6px 14px; white-space: nowrap; }
.order-detail-back { display: inline-block; font-size: 13px; color: var(--blue); text-decoration: none; margin-bottom: 20px; }
.order-detail-back:hover { text-decoration: underline; }

.order-detail-layout { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .order-detail-layout { grid-template-columns: 1fr; } }

.order-detail-items { display: flex; flex-direction: column; gap: 10px; }
.cart-item {
    display: flex; align-items: center; gap: 16px;
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px 24px; box-shadow: var(--shadow-sm);
}
.cart-item__img { width: 72px; height: 72px; flex-shrink: 0; border-radius: var(--radius); overflow: hidden; background: #fafafa; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; }
.cart-item__img img { max-width: 100%; max-height: 100%; object-fit: contain; }
.cart-item__info { flex: 1; min-width: 0; }
.cart-item__name { font-size: 14px; font-weight: 700; color: var(--black); text-decoration: none; display: block; line-height: 1.4; }
.cart-item__name:hover { color: var(--blue); }
.cart-item__article { font-size: 12px; color: var(--gray); margin-top: 4px; }
.cart-item__price-unit { font-size: 12px; color: var(--gray-light); margin-top: 4px; }
.cart-item__meta { font-size: 12px; color: var(--gray); margin-top: 4px; }
.cart-item__meta--status { color: #a15c00; }
.cart-item__meta-time { color: var(--gray-light); }
.cart-item__price { text-align: right; flex-shrink: 0; min-width: 100px; }
.cart-item__qty-label { font-size: 12px; color: var(--gray-light); }
.cart-item__sum { font-size: 16px; font-weight: 800; color: var(--black); margin-top: 2px; }

.order-detail-sidebar { position: sticky; top: 20px; }
.order-detail-summary { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
.order-detail-summary__title { font-size: 16px; font-weight: 700; margin: 0 0 16px; color: var(--black); }
.order-detail-summary__title:not(:first-child) { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }
.order-detail-summary__rows { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.order-detail-summary__row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--gray); }
.order-detail-summary__row span:last-child { text-align: right; color: var(--black); font-weight: 600; }
.order-detail-summary__total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 800; padding-top: 14px; border-top: 2px solid var(--border); color: var(--black); }
.order-detail-props { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: var(--black); }
.order-detail-props__name { color: var(--gray); }
</style>
