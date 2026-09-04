<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

CModule::IncludeModule('currency');

if (!empty($arResult['ERRORS']['FATAL'])) {
    foreach ($arResult['ERRORS']['FATAL'] as $error) ShowError($error);
    return;
}

if (!function_exists('pluralForm')) {
    function pluralForm($n, $one, $two, $five) {
        $n = abs($n) % 100;
        if ($n >= 11 && $n <= 19) return $five;
        $n = $n % 10;
        if ($n == 1) return $one;
        if ($n >= 2 && $n <= 4) return $two;
        return $five;
    }
}

// Не берём $arResult['INFO']['STATUS'] — ядровой компонент не подхватывает
// свежесозданные статусы (устаревший список внутри компонента, независимо от
// CACHE_TYPE=N самого компонента). Резолвим названия сами.
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');
$statusList = getOrderStatusNameMap();
$isMgr = isManager();

// Менеджеру — краткая сводка по позициям у поставщиков (поставщик + статус)
// прямо в списке заказов, без перехода в детали. Один запрос на все заказы
// страницы, а не по одному на заказ.
$supplierItemsByOrder = [];
if ($isMgr && !empty($arResult['ORDERS'])) {
    $orderIds = [];
    foreach ($arResult['ORDERS'] as $o2) {
        $oid = (int)($o2['ORDER']['ID'] ?? 0);
        if ($oid) $orderIds[] = $oid;
    }
    if ($orderIds) {
        try {
            $db = \Bitrix\Main\Application::getConnection();
            $rows = $db->query(
                "SELECT so.ORDER_ID, so.SUPPLIER_CODE, i.ARTICLE, i.BRAND, i.STATE_TEXT, i.STAGE
                 FROM b_supplier_order so
                 JOIN b_supplier_order_item i ON i.SUPPLIER_ORDER_ID = so.ID
                 WHERE so.ORDER_ID IN (" . implode(',', $orderIds) . ")"
            )->fetchAll();
            foreach ($rows as $row) {
                $supplierItemsByOrder[(int)$row['ORDER_ID']][] = $row;
            }
        } catch (\Throwable $e) {}
    }
}

if (empty($arResult['ORDERS'])): ?>
    <div class="empty-state">
        <div class="empty-state__icon"><svg class="icon"><use href="#icon-box"></use></svg></div>
        <h3>У вас пока нет заказов</h3>
        <p>Здесь будут отображаться ваши заказы</p>
        <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
    </div>
<?php else: ?>
    <div class="orders-list">
        <?php foreach ($arResult['ORDERS'] as $order):
            $o = $order['ORDER'];
            $basketItems = $order['BASKET_ITEMS'] ?? [];
            $shipment = $order['SHIPMENT'][0] ?? [];
            $payment = $order['PAYMENT'][0] ?? [];
            $statusName = $statusList[$o['STATUS_ID']] ?? $o['STATUS_ID'];
            $statusColor = getOrderStatusColor($o['STATUS_ID']);
            $orderId = (int)($o['ID'] ?? 0);
            $supplierItems = $supplierItemsByOrder[$orderId] ?? [];
            $isRefused = $o['STATUS_ID'] === 'SX';
        ?>
        <div class="order-card<?= $isMgr ? ' order-card--open' : '' ?>">
            <div class="order-card__header" onclick="this.closest('.order-card').classList.toggle('order-card--open')">
                <div class="order-card__header-left">
                    <span class="order-card__num">Заказ №<?= $o['ACCOUNT_NUMBER'] ?></span>
                    <span class="order-card__date"><?= $o['DATE_INSERT_FORMATED'] ?: $o['DATE_INSERT'] ?></span>
                    <span class="status-pill status-pill--<?= $statusColor ?>"><?= htmlspecialchars($statusName) ?></span>
                    <span class="order-card__count"><?= count($basketItems) ?> <?= pluralForm(count($basketItems), 'товар', 'товара', 'товаров') ?></span>
                </div>
                <div class="order-card__header-right">
                    <span class="order-card__price"><?= $o['FORMATED_PRICE'] ?></span>
                    <span class="order-card__badge order-card__badge--<?= $o['PAYED'] === 'Y' ? 'paid' : 'unpaid' ?>">
                        <?= $o['PAYED'] === 'Y' ? '<svg class="icon"><use href="#icon-check-circle"></use></svg> Оплачен' : '<svg class="icon"><use href="#icon-hourglass"></use></svg> Не оплачен' ?>
                    </span>
                    <span class="order-card__arrow">▾</span>
                </div>
            </div>
            <div class="order-card__body">
                <?php if ($isRefused): ?>
                <div class="status-banner status-banner--refused">
                    <span class="status-banner__icon">⚠</span>
                    <span>Заказ отменён — товар недоступен у поставщика (снят пользователем/поставщиком). Мы свяжемся с вами для уточнения деталей.</span>
                </div>
                <?php endif; ?>
                <?php if ($isMgr && !empty($supplierItems)): ?>
                <div class="order-card__suppliers">
                    <?php foreach ($supplierItems as $si):
                        $supplierLabel = $si['SUPPLIER_CODE'];
                        if (function_exists('getSupplierFactory')) {
                            $conn = getSupplierFactory()->get($si['SUPPLIER_CODE']);
                            if ($conn) $supplierLabel = $conn->getName();
                        }
                        $itemLabel = trim(($si['BRAND'] ?? '') . ' ' . ($si['ARTICLE'] ?? ''));
                        $stageColor = getSupplierStageColor($si['STAGE'] ?? null);
                    ?>
                    <div class="order-card__supplier-row">
                        <span class="order-card__supplier-name">
                            <?= htmlspecialchars($supplierLabel) ?><?php if ($itemLabel !== ''): ?> — <?= htmlspecialchars($itemLabel) ?><?php endif; ?>
                        </span>
                        <span class="status-pill status-pill--<?= $stageColor ?>"><?= htmlspecialchars((string)($si['STATE_TEXT'] ?? '') !== '' ? $si['STATE_TEXT'] : getSupplierStageLabel($si['STAGE'] ?? null)) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="order-card__products">
                    <?php foreach ($basketItems as $item): ?>
                    <div class="order-card__product">
                        <div class="order-card__product-img">
                            <?php
                            $imgSrc = '/local/templates/main_temp/assets/images/no-photo.png';
                            if (!empty($item['PRODUCT_ID'])) {
                                $el = CIBlockElement::GetByID($item['PRODUCT_ID'])->GetNextElement();
                                if ($el) {
                                    $f = $el->GetFields();
                                    $pic = $f['PREVIEW_PICTURE'] ?? $f['DETAIL_PICTURE'];
                                    if ($pic) {
                                        $p = CFile::GetPath($pic);
                                        if ($p) $imgSrc = $p;
                                    }
                                }
                            }
                            ?>
                            <img src="<?= $imgSrc ?>" alt="">
                        </div>
                        <div class="order-card__product-info">
                            <a href="/catalog/<?= $item['PRODUCT_ID'] ?>/" class="order-card__product-name"><?= htmlspecialchars($item['NAME']) ?></a>
                            <span class="order-card__product-meta"><?= $item['QUANTITY'] ?> шт. × <?= CurrencyFormat($item['PRICE'], 'RUB') ?></span>
                        </div>
                        <div class="order-card__product-price"><?= CurrencyFormat($item['PRICE'] * $item['QUANTITY'], 'RUB') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="order-card__info">
                    <div class="order-card__info-item">
                        <span class="order-card__info-label">Статус заказа</span>
                        <span class="order-card__info-value"><span class="status-pill status-pill--<?= $statusColor ?>"><?= htmlspecialchars($statusName) ?></span></span>
                    </div>
                    <div class="order-card__info-item">
                        <span class="order-card__info-label">Доставка</span>
                        <span class="order-card__info-value"><?= htmlspecialchars($shipment['DELIVERY_NAME'] ?? '—') ?></span>
                    </div>
                    <div class="order-card__info-item">
                        <span class="order-card__info-label">Статус доставки</span>
                        <span class="order-card__info-value"><?= htmlspecialchars($shipment['DELIVERY_STATUS_NAME'] ?? $shipment['STATUS_NAME'] ?? '—') ?></span>
                    </div>
                    <div class="order-card__info-item">
                        <span class="order-card__info-label">Способ оплаты</span>
                        <span class="order-card__info-value"><?= htmlspecialchars($payment['PAY_SYSTEM_NAME'] ?? '—') ?></span>
                    </div>
                </div>
                <div class="order-card__actions">
                    <?php if (!empty($o['URL_TO_COPY'])): ?>
                        <a href="<?= htmlspecialcharsbx($o['URL_TO_COPY']) ?>" class="btn btn--outline btn--sm"><svg class="icon"><use href="#icon-refresh"></use></svg> Повторить</a>
                    <?php endif; ?>
                    <?php if ($o['PAYED'] !== 'Y' && !empty($payment['PSA_ACTION_FILE'])): ?>
                        <a href="<?= htmlspecialcharsbx($payment['PSA_ACTION_FILE']) ?>" class="btn btn--primary btn--sm"><svg class="icon"><use href="#icon-card"></use></svg> Оплатить</a>
                    <?php endif; ?>
                    <?php if (!empty($o['URL_TO_DETAIL'])): ?>
                        <a href="<?= htmlspecialcharsbx($o['URL_TO_DETAIL']) ?>" class="btn btn--white btn--sm"><svg class="icon"><use href="#icon-list"></use></svg> Подробнее</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?= $arResult['NAV_STRING'] ?>
<?php endif; ?>

<style>
.orders-list { display: flex; flex-direction: column; gap: 12px; }
.order-card {
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow-sm); overflow: hidden; transition: box-shadow 0.15s;
}
.order-card:hover { box-shadow: var(--shadow); }
.order-card__header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 20px; cursor: pointer; user-select: none; gap: 12px; flex-wrap: wrap;
}
.order-card__header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.order-card__header-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.order-card__num { font-weight: 700; font-size: 15px; color: var(--black); }
.order-card__date { font-size: 13px; color: var(--gray); }
.order-card__count { font-size: 13px; color: var(--gray-light); }
.order-card__price { font-weight: 800; font-size: 16px; white-space: nowrap; }
.order-card__badge { padding: 3px 10px; border-radius: var(--radius); font-size: 12px; font-weight: 700; }
.order-card__badge--paid { background: rgba(77,205,113,0.12); color: #3a9d4f; }
.order-card__badge--unpaid { background: rgba(230,76,70,0.1); color: var(--red); }
.order-card__arrow { font-size: 14px; color: var(--gray); transition: transform 0.2s; }
.order-card__body { display: none; padding: 0 20px 20px; border-top: 1px solid var(--border); }
.order-card--open .order-card__body { display: block; }
.order-card--open .order-card__arrow { transform: rotate(180deg); }
.order-card--open { box-shadow: var(--shadow); border-color: var(--blue); }
.order-card__suppliers { display: flex; flex-direction: column; gap: 6px; padding: 14px 0; border-bottom: 1px solid #eee; }
.order-card__supplier-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12px; flex-wrap: wrap; }
.order-card__supplier-name { color: var(--gray); font-weight: 600; }
.order-card__products { display: flex; flex-direction: column; gap: 10px; padding: 16px 0; }
.order-card__product { display: flex; align-items: center; gap: 14px; padding: 10px 12px; background: var(--bg); border-radius: var(--radius); }
.order-card__product-img { width: 52px; height: 52px; border-radius: var(--radius); overflow: hidden; background: #fff; border: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.order-card__product-img img { max-width: 100%; max-height: 100%; object-fit: contain; }
.order-card__product-info { flex: 1; min-width: 0; }
.order-card__product-name { font-weight: 600; font-size: 13px; color: var(--black); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.order-card__product-name:hover { color: var(--blue); }
.order-card__product-meta { font-size: 12px; color: var(--gray-light); display: block; margin-top: 2px; }
.order-card__product-price { font-weight: 700; font-size: 14px; white-space: nowrap; flex-shrink: 0; }
.order-card__info { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; padding: 12px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; }
.order-card__info-item { display: flex; flex-direction: column; gap: 2px; }
.order-card__info-label { font-size: 11px; color: var(--gray-light); text-transform: uppercase; letter-spacing: 0.03em; font-weight: 700; }
.order-card__info-value { font-size: 13px; font-weight: 600; color: var(--black); }
.order-card__actions { display: flex; gap: 8px; padding-top: 12px; flex-wrap: wrap; }
@media (max-width: 600px) {
    .order-card__header { flex-direction: column; align-items: flex-start; }
    .order-card__header-right { width: 100%; justify-content: space-between; }
    .order-card__info { grid-template-columns: 1fr; }
    .order-card__product { flex-wrap: wrap; }
}
</style>
