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

// Данные поставщика (поставщик + бренд/артикул + статус) для каждой ПОЗИЦИИ
// заказа — не отдельным блоком-сводкой, а привязанные к конкретной строке
// товара через BASKET_ITEM_ID (1:1 с b_sale_basket.ID, см. saveSupplierOrderRecord()
// в order_create_handler.php). Менеджеру показываем целиком, остальным нужен
// только артикул для поиска (см. фильтр ниже). Один запрос на все заказы
// страницы, а не по одному.
$supplierItemsByOrder = [];       // orderId => плоский список (для фильтра по артикулу/поставщику)
$supplierItemsByBasketId = [];    // orderId => [basketItemId => строка] (для привязки к конкретному товару)
if (!empty($arResult['ORDERS'])) {
    $orderIds = [];
    foreach ($arResult['ORDERS'] as $o2) {
        $oid = (int)($o2['ORDER']['ID'] ?? 0);
        if ($oid) $orderIds[] = $oid;
    }
    if ($orderIds) {
        try {
            $db = \Bitrix\Main\Application::getConnection();
            $rows = $db->query(
                "SELECT so.ORDER_ID, so.SUPPLIER_CODE, i.BASKET_ITEM_ID, i.ARTICLE, i.BRAND, i.STATE_TEXT, i.STAGE
                 FROM b_supplier_order so
                 JOIN b_supplier_order_item i ON i.SUPPLIER_ORDER_ID = so.ID
                 WHERE so.ORDER_ID IN (" . implode(',', $orderIds) . ")"
            )->fetchAll();
            foreach ($rows as $row) {
                $oid = (int)$row['ORDER_ID'];
                $supplierItemsByOrder[$oid][] = $row;
                $bid = (int)($row['BASKET_ITEM_ID'] ?? 0);
                if ($bid) $supplierItemsByBasketId[$oid][$bid] = $row;
            }
        } catch (\Throwable $e) {}
    }
}

// Артикул своих (не поставщика) позиций хранится не в отдельной таблице, а в
// свойстве CML2_ARTICLE карточки товара — тянем одним batch-запросом по всем
// товарам страницы, чтобы поиск по артикулу не плодил запрос на позицию.
// Без фильтра по IBLOCK_ID: заказы могут ссылаться на товары как из текущих
// каталожных инфоблоков (55/56/57), так и на старые из 42 (исторические
// заказы) — ID элемента уникален по всей базе, фильтр по нему достаточен.
$productArticleById = [];
if (!empty($arResult['ORDERS']) && CModule::IncludeModule('iblock')) {
    $productIds = [];
    foreach ($arResult['ORDERS'] as $o2) {
        foreach (($o2['BASKET_ITEMS'] ?? []) as $bi) {
            $pid = (int)($bi['PRODUCT_ID'] ?? 0);
            if ($pid) $productIds[$pid] = true;
        }
    }
    if ($productIds) {
        try {
            $res = CIBlockElement::GetList(
                [],
                ['ID' => array_keys($productIds)],
                false,
                false,
                ['ID', 'PROPERTY_CML2_ARTICLE']
            );
            while ($row = $res->Fetch()) {
                $productArticleById[(int)$row['ID']] = (string)($row['PROPERTY_CML2_ARTICLE_VALUE'] ?? '');
            }
        } catch (\Throwable $e) {}
    }
}

// Отмена заказа покупателем (POST cancel_order, см. order_actions.php).
handleCustomerOrderCancelRequest();
$cancelFlash = (string)($_GET['order_cancel'] ?? '');
$cancelFlashOrder = (int)($_GET['order_cancel_id'] ?? 0);

// Заказы с позициями "под заказ" у поставщика (свойство корзины SUPPLIER_NAME)
// — их покупатель отменить сам не может. Одним запросом на всю страницу; сама
// отмена всё равно перепроверяет условия на сервере (cancelOrderByCustomer()).
$ordersWithSupplierItems = [];
if (!empty($arResult['ORDERS'])) {
    $basketToOrder = [];
    foreach ($arResult['ORDERS'] as $o2) {
        foreach (($o2['BASKET_ITEMS'] ?? []) as $bi) {
            $bid = (int)($bi['ID'] ?? 0);
            if ($bid) $basketToOrder[$bid] = (int)($o2['ORDER']['ID'] ?? 0);
        }
    }
    if ($basketToOrder) {
        try {
            $rows = \Bitrix\Main\Application::getConnection()->query(
                "SELECT DISTINCT BASKET_ID FROM b_sale_basket_props
                 WHERE CODE = 'SUPPLIER_NAME' AND VALUE <> ''
                   AND BASKET_ID IN (" . implode(',', array_keys($basketToOrder)) . ")"
            )->fetchAll();
            foreach ($rows as $row) {
                $ordersWithSupplierItems[$basketToOrder[(int)$row['BASKET_ID']] ?? 0] = true;
            }
        } catch (\Throwable $e) {}
    }
}

// Поставщики "у которых оформлены заказы" — список берём из уже загруженной
// сводки по текущему набору заказов, а не из полного реестра коннекторов:
// в фильтр должны попадать только реально встречающиеся варианты.
$supplierOptions = [];
if ($isMgr) {
    $supplierCodesSeen = [];
    foreach ($supplierItemsByOrder as $items) {
        foreach ($items as $it) {
            $code = (string)($it['SUPPLIER_CODE'] ?? '');
            if ($code !== '') $supplierCodesSeen[$code] = true;
        }
    }
    foreach (array_keys($supplierCodesSeen) as $code) {
        $label = $code;
        if (function_exists('getSupplierFactory')) {
            $conn = getSupplierFactory()->get($code);
            if ($conn) $label = $conn->getName();
        }
        $supplierOptions[$code] = $label;
    }
    asort($supplierOptions);
}

// Способы доставки "которые реально встречаются" в заказах пользователя —
// по аналогии с $supplierOptions выше, но доступно всем (не только менеджеру):
// способ доставки — это то, что сам покупатель выбирал при оформлении.
$deliveryOptions = [];
foreach (($arResult['ORDERS'] ?? []) as $o2) {
    $sh = $o2['SHIPMENT'][0] ?? [];
    $did = (string)($sh['DELIVERY_ID'] ?? '');
    if ($did !== '') $deliveryOptions[$did] = (string)($sh['DELIVERY_NAME'] ?? $did);
}
asort($deliveryOptions);

// ---- Фильтр шапки: даты, статус, доставка, поставщик (только менеджер), поиск по артикулу ----
$fDateFrom = trim((string)($_GET['date_from'] ?? ''));
$fDateTo   = trim((string)($_GET['date_to'] ?? ''));
$fStatus   = trim((string)($_GET['status'] ?? ''));
$fDelivery = trim((string)($_GET['delivery'] ?? ''));
$fSupplier = $isMgr ? trim((string)($_GET['supplier'] ?? '')) : '';
$fQuery    = trim((string)($_GET['q'] ?? ''));
$hasFilters = $fDateFrom !== '' || $fDateTo !== '' || $fStatus !== '' || $fDelivery !== '' || $fSupplier !== '' || $fQuery !== '';

$ordersToShow = $arResult['ORDERS'];
if ($hasFilters) {
    $dateFromTs = $fDateFrom !== '' ? strtotime($fDateFrom . ' 00:00:00') : null;
    $dateToTs = $fDateTo !== '' ? strtotime($fDateTo . ' 23:59:59') : null;

    $ordersToShow = array_filter($ordersToShow, function ($order) use (
        $dateFromTs, $dateToTs, $fStatus, $fDelivery, $fSupplier, $fQuery,
        $supplierItemsByOrder, $productArticleById
    ) {
        $o = $order['ORDER'];
        $orderId = (int)($o['ID'] ?? 0);

        if ($dateFromTs !== null || $dateToTs !== null) {
            $orderTs = strtotime((string)($o['DATE_INSERT'] ?? ''));
            if ($orderTs === false) return false;
            if ($dateFromTs !== null && $orderTs < $dateFromTs) return false;
            if ($dateToTs !== null && $orderTs > $dateToTs) return false;
        }

        if ($fStatus !== '' && ($o['STATUS_ID'] ?? '') !== $fStatus) return false;

        if ($fDelivery !== '') {
            $orderDeliveryId = (string)(($order['SHIPMENT'][0]['DELIVERY_ID'] ?? ''));
            if ($orderDeliveryId !== $fDelivery) return false;
        }

        $items = $supplierItemsByOrder[$orderId] ?? [];

        if ($fSupplier !== '') {
            $hasSupplier = false;
            foreach ($items as $it) {
                if (($it['SUPPLIER_CODE'] ?? '') === $fSupplier) { $hasSupplier = true; break; }
            }
            if (!$hasSupplier) return false;
        }

        if ($fQuery !== '') {
            $found = false;
            foreach ($items as $it) {
                if (mb_stripos((string)($it['ARTICLE'] ?? ''), $fQuery) !== false) { $found = true; break; }
            }
            if (!$found) {
                foreach (($order['BASKET_ITEMS'] ?? []) as $bi) {
                    $pid = (int)($bi['PRODUCT_ID'] ?? 0);
                    $art = $productArticleById[$pid] ?? '';
                    if ($art !== '' && mb_stripos($art, $fQuery) !== false) { $found = true; break; }
                }
            }
            if (!$found) return false;
        }

        return true;
    });
}
?>

<form method="get" class="orders-filter">
    <div class="orders-filter__row">
        <div class="orders-filter__field orders-filter__field--search">
            <label for="ordersFilterQ">Поиск по артикулу</label>
            <input type="text" id="ordersFilterQ" name="q" value="<?= htmlspecialchars($fQuery) ?>" placeholder="Например, 04465-33471">
        </div>
        <div class="orders-filter__field">
            <label for="ordersFilterDateFrom">Дата с</label>
            <input type="date" id="ordersFilterDateFrom" name="date_from" value="<?= htmlspecialchars($fDateFrom) ?>">
        </div>
        <div class="orders-filter__field">
            <label for="ordersFilterDateTo">Дата по</label>
            <input type="date" id="ordersFilterDateTo" name="date_to" value="<?= htmlspecialchars($fDateTo) ?>">
        </div>
        <div class="orders-filter__field">
            <label for="ordersFilterStatus">Статус заказа</label>
            <select id="ordersFilterStatus" name="status">
                <option value="">Все статусы</option>
                <?php foreach ($statusList as $sid => $sname): ?>
                    <option value="<?= htmlspecialchars($sid) ?>"<?= $fStatus === (string)$sid ? ' selected' : '' ?>><?= htmlspecialchars($sname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($deliveryOptions): ?>
        <div class="orders-filter__field">
            <label for="ordersFilterDelivery">Способ доставки</label>
            <select id="ordersFilterDelivery" name="delivery">
                <option value="">Любой способ</option>
                <?php foreach ($deliveryOptions as $did => $dname): ?>
                    <?php $didStr = (string)$did; // числовой ключ массива PHP автоматически приводит к int — без явного (string) строгое сравнение с $_GET (всегда строкой) не срабатывает ?>
                    <option value="<?= htmlspecialchars($didStr) ?>"<?= $fDelivery === $didStr ? ' selected' : '' ?>><?= htmlspecialchars($dname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($isMgr): ?>
        <div class="orders-filter__field">
            <label for="ordersFilterSupplier">Поставщик</label>
            <select id="ordersFilterSupplier" name="supplier">
                <option value="">Все поставщики</option>
                <?php foreach ($supplierOptions as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>"<?= $fSupplier === $code ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="orders-filter__actions">
            <button type="submit" class="btn btn--primary btn--sm">Применить</button>
            <?php if ($hasFilters): ?>
                <a href="<?= htmlspecialchars($APPLICATION->GetCurPage()) ?>" class="btn btn--white btn--sm">Сбросить</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (empty($ordersToShow)): ?>
    <div class="empty-state">
        <div class="empty-state__icon"><svg class="icon"><use href="#icon-box"></use></svg></div>
        <?php if ($hasFilters): ?>
            <h3>Заказы не найдены</h3>
            <p>Попробуйте изменить параметры фильтра или поиска</p>
        <?php else: ?>
            <h3>У вас пока нет заказов</h3>
            <p>Здесь будут отображаться ваши заказы</p>
            <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($cancelFlash === 'ok'): ?>
    <div class="status-banner status-banner--ok" style="margin-bottom: 16px;">
        <span class="status-banner__icon">✓</span>
        <span>Заказ №<?= $cancelFlashOrder ?> отменён.</span>
    </div>
    <?php elseif ($cancelFlash !== ''): ?>
    <div class="status-banner status-banner--refused" style="margin-bottom: 16px;">
        <span class="status-banner__icon">⚠</span>
        <span>Не удалось отменить заказ №<?= $cancelFlashOrder ?>: <?= htmlspecialchars($cancelFlash) ?></span>
    </div>
    <?php endif; ?>
    <div class="orders-list">
        <?php foreach ($ordersToShow as $order):
            $o = $order['ORDER'];
            $basketItems = $order['BASKET_ITEMS'] ?? [];
            $shipment = $order['SHIPMENT'][0] ?? [];
            $payment = $order['PAYMENT'] ? (reset($order['PAYMENT']) ?: []) : [];
            // У заказов, оформленных до 06.10.2026, оплата сохранялась только с
            // PAY_SYSTEM_ID, без PAY_SYSTEM_NAME (NULL в базе) — берём название
            // самой платёжной системы по её ID.
            $paymentName = trim((string)($payment['PAY_SYSTEM_NAME'] ?? ''));
            if ($paymentName === '' && (int)($payment['PAY_SYSTEM_ID'] ?? 0) > 0) {
                $paymentName = (string)(getPaySystemRow((int)$payment['PAY_SYSTEM_ID'])['NAME'] ?? '');
            }
            $isCanceled = ($o['CANCELED'] ?? 'N') === 'Y';
            $statusName = $isCanceled ? 'Отменён' : ($statusList[$o['STATUS_ID']] ?? $o['STATUS_ID']);
            $statusColor = $isCanceled ? 'red' : getOrderStatusColor($o['STATUS_ID']);
            $orderId = (int)($o['ID'] ?? 0);
            $supplierItems = $supplierItemsByOrder[$orderId] ?? [];
            $supplierItemsForBasket = $supplierItemsByBasketId[$orderId] ?? [];
            $isRefused = !$isCanceled && $o['STATUS_ID'] === 'SX';
            $isOrderPaid = $o['PAYED'] === 'Y';
            foreach (($order['PAYMENT'] ?? []) as $p2) {
                if (($p2['PAID'] ?? 'N') === 'Y') { $isOrderPaid = true; break; }
            }
            $isOwnOrder = (int)($o['USER_ID'] ?? 0) === (int)$USER->GetID();
            $canCancel = $isOwnOrder && !$isCanceled && !$isOrderPaid
                && $o['STATUS_ID'] !== 'F' && empty($ordersWithSupplierItems[$orderId]);
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
                <?php if ($isCanceled): ?>
                <div class="status-banner status-banner--refused">
                    <span class="status-banner__icon">⚠</span>
                    <span><?= htmlspecialchars((string)($o['REASON_CANCELED'] ?? '') !== '' ? $o['REASON_CANCELED'] : 'Заказ отменён.') ?></span>
                </div>
                <?php elseif ($isRefused): ?>
                <div class="status-banner status-banner--refused">
                    <span class="status-banner__icon">⚠</span>
                    <span>Заказ отменён — товар недоступен у поставщика (снят пользователем/поставщиком). Мы свяжемся с вами для уточнения деталей.</span>
                </div>
                <?php endif; ?>
                <div class="order-card__products">
                    <?php foreach ($basketItems as $item):
                        // Поставщик/бренд/артикул/статус — конкретно ЭТОЙ позиции корзины
                        // (см. BASKET_ITEM_ID в b_supplier_order_item), а не общий список
                        // отдельно от товаров: для служебной "заказной" позиции у каждого
                        // поставщика один и тот же PRODUCT_ID, поэтому различить строки
                        // можно только по basket_item_id, не по товару.
                        $bItemId = (int)($item['ID'] ?? 0);
                        $si = $isMgr ? ($supplierItemsForBasket[$bItemId] ?? null) : null;
                        $siSupplierLabel = '';
                        $siArticleLabel  = '';
                        $siStageColor    = '';
                        $siStageText     = '';
                        if ($si) {
                            $siSupplierLabel = $si['SUPPLIER_CODE'];
                            if (function_exists('getSupplierFactory')) {
                                $conn = getSupplierFactory()->get($si['SUPPLIER_CODE']);
                                if ($conn) $siSupplierLabel = $conn->getName();
                            }
                            $siArticleLabel = trim(($si['BRAND'] ?? '') . ' ' . ($si['ARTICLE'] ?? ''));
                            $siStageColor   = getSupplierStageColor($si['STAGE'] ?? null);
                            $siStageText    = (string)($si['STATE_TEXT'] ?? '') !== '' ? $si['STATE_TEXT'] : getSupplierStageLabel($si['STAGE'] ?? null);
                        }
                    ?>
                    <div class="order-card__product">
                        <div class="order-card__product-img">
                            <?php
                            $imgSrc = '/local/templates/lider_modern/assets/images/legacy/no_photo.png';
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
                            <?php if ($si): ?>
                            <div class="order-card__product-supplier">
                                <span class="order-card__supplier-name">
                                    <?= htmlspecialchars($siSupplierLabel) ?><?php if ($siArticleLabel !== ''): ?> — <?= htmlspecialchars($siArticleLabel) ?><?php endif; ?>
                                </span>
                                <span class="status-pill status-pill--<?= $siStageColor ?>"><?= htmlspecialchars($siStageText) ?></span>
                            </div>
                            <?php endif; ?>
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
                        <span class="order-card__info-value"><?= htmlspecialchars($paymentName !== '' ? $paymentName : '—') ?></span>
                    </div>
                </div>
                <div class="order-card__actions">
                    <?php if (!empty($o['URL_TO_COPY'])): ?>
                        <a href="<?= htmlspecialcharsbx($o['URL_TO_COPY']) ?>" class="btn btn--outline btn--sm"><svg class="icon"><use href="#icon-refresh"></use></svg> Повторить</a>
                    <?php endif; ?>
                    <?php
                    // Форма оплаты — на странице заказа (getOrderOnlinePayForms()), а не на
                    // старой /personal/order/payment/. Заказ на наличных там же можно
                    // переключить на карту (getOrderSwitchablePaySystems()).
                    $listPaySystemId = (int)($payment['PAY_SYSTEM_ID'] ?? 0);
                    $showPayButton = $isOwnOrder && !$isCanceled && !$isOrderPaid && $orderId > 0
                        && $o['STATUS_ID'] !== 'F' && !isManagerPaySystem($listPaySystemId);
                    ?>
                    <?php if ($showPayButton): ?>
                        <a href="/personal/orders/?ID=<?= $orderId ?>#pay" class="btn btn--primary btn--sm"><svg class="icon"><use href="#icon-card"></use></svg> <?= isCashPaySystem($listPaySystemId) ? 'Оплатить картой' : 'Оплатить' ?></a>
                    <?php endif; ?>
                    <?php
                    // Доставка курьером из заказа: заказной товар — в статусе «Товар готов
                    // к выдаче», товар из наличия — после полной оплаты. Дешёвый отсев по
                    // данным списка, полная проверка — getOrderDeliveryRequestBlockReason().
                    $canRequestDelivery = false;
                    $hasSupplierInOrder = !empty($ordersWithSupplierItems[$orderId]);
                    if ($isOwnOrder && !$isCanceled && $orderId > 0
                        && ($hasSupplierInOrder ? $o['STATUS_ID'] === 'SR' : $o['PAYED'] === 'Y')) {
                        try {
                            $deliveryOrder = \Bitrix\Sale\Order::load($orderId);
                            $canRequestDelivery = $deliveryOrder && getOrderDeliveryRequestBlockReason($deliveryOrder) === null;
                        } catch (\Throwable $e) {}
                    }
                    // Пока доставка недоступна — подсказываем, когда станет (только для
                    // заказов на самовывоз, ещё не выданных и не отменённых).
                    $deliveryHint = '';
                    if (!$canRequestDelivery && $isOwnOrder && !$isCanceled && !in_array($o['STATUS_ID'], ['F', 'SX'], true)
                        && ($shipment['DEDUCTED'] ?? 'N') !== 'Y' && isPickupDelivery((int)($shipment['DELIVERY_ID'] ?? 0))) {
                        if ($hasSupplierInOrder && $o['STATUS_ID'] !== 'SR') {
                            $deliveryHint = 'Доставку курьером можно будет оформить, когда товар будет готов к выдаче';
                        } elseif (!$hasSupplierInOrder && $o['PAYED'] !== 'Y') {
                            $deliveryHint = 'Оплатите заказ картой — после оплаты можно оформить доставку курьером';
                        }
                    }
                    ?>
                    <?php if ($canRequestDelivery): ?>
                        <a href="/personal/orders/?ID=<?= $orderId ?>#delivery" class="btn btn--primary btn--sm"><svg class="icon"><use href="#icon-truck"></use></svg> Оформить доставку</a>
                    <?php endif; ?>
                    <?php if ($orderId > 0): ?>
                        <a href="/personal/orders/?ID=<?= $orderId ?>" class="btn btn--white btn--sm"><svg class="icon"><use href="#icon-list"></use></svg> Подробнее</a>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                        <form method="post" class="order-card__cancel-form" onsubmit="return confirm('Отменить заказ №<?= htmlspecialcharsbx($o['ACCOUNT_NUMBER']) ?>?');">
                            <?= bitrix_sessid_post() ?>
                            <input type="hidden" name="cancel_order" value="<?= $orderId ?>">
                            <button type="submit" class="btn btn--sm order-card__cancel-btn">Отменить</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($deliveryHint !== ''): ?>
                        <div class="order-card__delivery-hint"><svg class="icon"><use href="#icon-truck"></use></svg> <?= htmlspecialchars($deliveryHint) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$hasFilters): ?>
        <?= $arResult['NAV_STRING'] ?>
    <?php endif; ?>
<?php endif; ?>

<style>
/* .lk-content уже стилизует чужие "сырые" формы (гостевой вход) правилом
   `form:not(.profile-form)` — max-width:340px и своя раскладка для
   input[type=text]. Перебиваем явно, иначе фильтр схлопывается в узкую колонку. */
.lk-content form.orders-filter { max-width: none !important; }
.lk-content form.orders-filter input[type="text"] {
    display: block !important; width: 100% !important; margin: 0 !important;
    padding: 8px 10px !important; border: 1px solid var(--border) !important;
    border-radius: var(--radius) !important; font-size: 13px !important;
    height: 36px !important; box-sizing: border-box !important;
}
.orders-filter {
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow-sm); padding: 16px 20px; margin-bottom: 16px;
    position: sticky; top: calc(var(--header-h, 72px) + 12px); z-index: 50;
}
.orders-filter__row { display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; width: 100%; }
.orders-filter__field { display: flex; flex-direction: column; gap: 4px; flex: 1 1 0; min-width: 140px; }
.orders-filter__field--search { flex: 1.6 1 0; }
.orders-filter__field label { font-size: 12px; color: var(--gray); font-weight: 700; }
.orders-filter__field input, .orders-filter__field select {
    width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: var(--radius);
    font-size: 13px; color: var(--black); background: #fff; height: 36px; box-sizing: border-box;
}
.orders-filter__actions { display: flex; gap: 8px; flex: 0 0 auto; }
@media (max-width: 900px) {
    .orders-filter { position: static; }
    .orders-filter__row { flex-direction: column; align-items: stretch; }
    .orders-filter__field { min-width: 0; }
}
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
.order-card__badge { padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.order-card__badge--paid { background: rgba(77,205,113,0.12); color: #3a9d4f; }
.order-card__badge--unpaid { background: rgba(230,76,70,0.1); color: var(--red); }
.order-card__arrow { font-size: 14px; color: var(--gray); transition: transform 0.2s; }
.order-card__body { display: none; padding: 0 20px 20px; border-top: 1px solid var(--border); }
.order-card--open .order-card__body { display: block; }
.order-card--open .order-card__arrow { transform: rotate(180deg); }
.order-card--open { box-shadow: var(--shadow); border-color: var(--blue); }
.order-card__products { display: flex; flex-direction: column; gap: 10px; padding: 16px 0; }
.order-card__product-supplier { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
.order-card__supplier-name { color: var(--gray); font-weight: 600; font-size: 12px; }
.order-card__product { display: flex; align-items: center; gap: 14px; padding: 10px 12px; background: var(--bg); border-radius: var(--radius); }
.order-card__product-img { width: 52px; height: 52px; border-radius: var(--radius); overflow: hidden; background: #fff; border: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.order-card__product-img img { max-width: 100%; max-height: 100%; object-fit: contain; }
.order-card__product-info { flex: 1; min-width: 0; }
.order-card__product-name { font-weight: 600; font-size: 13px; color: var(--black); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.order-card__product-name:hover { color: var(--blue); }
.order-card__product-meta { font-size: 12px; color: var(--gray-light); display: block; margin-top: 2px; }
.order-card__product-price { font-weight: 700; font-size: 14px; white-space: nowrap; flex-shrink: 0; }
.order-card__info { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; padding: 12px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; }
.order-card__info-item { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
/* Длинные статусы ("Оплата поступила, заказ обрабатывается") не должны
   вылезать в соседнюю колонку — .status-pill по умолчанию nowrap. */
.order-card__info-value .status-pill { white-space: normal; max-width: 100%; text-align: left; }
.order-card__info-label { font-size: 12px; color: var(--gray-light); font-weight: 700; }
.order-card__info-value { font-size: 13px; font-weight: 600; color: var(--black); }
.order-card__actions { display: flex; gap: 8px; padding-top: 12px; flex-wrap: wrap; }
.order-card__cancel-form { margin: 0 0 0 auto; }
.order-card__cancel-btn { background: transparent; border: 1.5px solid var(--border); color: var(--red, #e53935); cursor: pointer; font-family: inherit; }
.order-card__cancel-btn:hover { border-color: var(--red, #e53935); background: rgba(229,57,53,0.05); }
.status-banner--ok { background: rgba(46,160,67,0.08); color: #1f7a33; }
.order-card__delivery-hint { flex-basis: 100%; order: -1; display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--gray); }
.order-card__delivery-hint .icon { width: 14px; height: 14px; flex-shrink: 0; color: var(--blue); }
@media (max-width: 600px) {
    .order-card__header { flex-direction: column; align-items: flex-start; }
    .order-card__header-right { width: 100%; justify-content: space-between; }
    .order-card__info { grid-template-columns: 1fr; }
    .order-card__product { flex-wrap: wrap; }
}
</style>
