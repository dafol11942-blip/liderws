<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CModule::IncludeModule('sale');
CModule::IncludeModule('iblock');
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/order_create_handler.php");

// isManager() определена в init_pricing.php, который уже требует
// order_create_handler.php выше — отдельный require не нужен.
$isMgr = isManager();

// Товары из корзины
$basketItems = [];
$bRes = CSaleBasket::GetList(['NAME' => 'ASC'], [
    'FUSER_ID' => CSaleBasket::GetBasketUserID(),
    'ORDER_ID' => 'NULL',
    'LID' => SITE_ID
]);
$totalBasket = 0; $totalBasketQty = 0;
$hasNonReturnableItem = false;
while ($b = $bRes->Fetch()) {
    $b['PRICE_NUM'] = (float)$b['PRICE'];
    $b['QTY'] = (int)$b['QUANTITY'];
    $b['SUM_NUM'] = $b['PRICE_NUM'] * $b['QTY'];
    $b['PRICE_FMT'] = number_format($b['PRICE_NUM'], 0, ',', ' ') . ' ₽';
    $b['SUM_FMT'] = number_format($b['SUM_NUM'], 0, ',', ' ') . ' ₽';
    $b['IMG'] = SITE_TEMPLATE_PATH . '/assets/images/no-photo.png';
    if ($b['PRODUCT_ID'] > 0) {
        $el = CIBlockElement::GetByID($b['PRODUCT_ID'])->GetNextElement();
        if ($el) {
            $f = $el->GetFields();
            $pic = $f['PREVIEW_PICTURE'] ?? $f['DETAIL_PICTURE'];
            if ($pic) { $p = CFile::GetPath($pic); if ($p) $b['IMG'] = $p; }
        }
    }

    // Свойства позиции — артикул/бренд/поставщик/склад/срок доставки, тот же
    // источник, что и в корзине (sale.basket.basket/lider_style/template.php).
    $props = [];
    $propsRes = CSaleBasket::GetPropsList([], ['BASKET_ID' => $b['ID']]);
    while ($pr = $propsRes->Fetch()) {
        $props[$pr['CODE']] = $pr['VALUE'];
    }

    $b['RETURNABLE'] = ($props['SUPPLIER_RETURNABLE'] ?? 'Y') !== 'N';
    if (!$b['RETURNABLE']) $hasNonReturnableItem = true;

    $supplierCode = $props['SUPPLIER_NAME'] ?? '';
    $b['ARTICLE'] = $props['SUPPLIER_ARTICLE'] ?? '';
    $b['BRAND']   = $props['SUPPLIER_BRAND'] ?? '';

    // Товар со своего склада — своих артикула/бренда в свойствах корзины
    // нет, берём их прямо с элемента каталога (как в корзине).
    if ($b['ARTICLE'] === '' && $b['PRODUCT_ID'] > 0) {
        $artRes = CIBlockElement::GetProperty(42, $b['PRODUCT_ID'], [], ['CODE' => 'CML2_ARTICLE']);
        if ($artRow = $artRes->Fetch()) {
            $b['ARTICLE'] = (string)($artRow['VALUE'] ?? '');
        }
    }
    if ($b['BRAND'] === '' && $b['PRODUCT_ID'] > 0) {
        $brandRes = CIBlockElement::GetProperty(42, $b['PRODUCT_ID'], [], ['CODE' => 'CML2_MANUFACTURER']);
        if ($brandRow = $brandRes->Fetch()) {
            $b['BRAND'] = (string)($brandRow['VALUE_ENUM'] ?? $brandRow['VALUE'] ?? '');
        }
    }

    $deliveryLabel = $props['SUPPLIER_DELIVERY_LABEL'] ?? '';
    $deliveryTime  = $props['SUPPLIER_DELIVERY_TIME'] ?? '';
    $deliveryDays  = isset($props['SUPPLIER_DELIVERY_DAYS']) ? (int)$props['SUPPLIER_DELIVERY_DAYS'] : null;
    if ($deliveryLabel !== '') {
        $b['DELIVERY_TEXT'] = $deliveryLabel . ($deliveryTime !== '' ? ' ' . $deliveryTime : '');
    } elseif ($deliveryDays !== null && $deliveryDays >= 0) {
        $b['DELIVERY_TEXT'] = $deliveryDays . ' дн.';
    } else {
        $b['DELIVERY_TEXT'] = '';
    }

    // Поставщик/склад — только для менеджеров (клиенту реальный склад не показываем).
    $b['SUPPLIER_CODE'] = $supplierCode;
    if ($isMgr && $supplierCode !== '') {
        $supplierLabel = $supplierCode;
        if (function_exists('getSupplierFactory')) {
            $conn = getSupplierFactory()->get($supplierCode);
            if ($conn) $supplierLabel = $conn->getName();
        }
        $b['SUPPLIER_LABEL'] = $supplierLabel;
        $b['WAREHOUSE'] = $props['SUPPLIER_WAREHOUSE'] ?? '';
    }

    $totalBasket += $b['SUM_NUM'];
    $totalBasketQty += $b['QTY'];
    $basketItems[] = $b;
}
$totalBasketFmt = number_format($totalBasket, 0, ',', ' ') . ' ₽';

// Свойства
$userProps = $arResult['ORDER_PROP']['USER_PROPS_Y'] ?? ($arResult['ORDER_PROP']['USER_PROPS_N'] ?? []);
$deliveries = $arResult['DELIVERY'] ?? [];
$payments = $arResult['PAY_SYSTEM'] ?? [];

// Номер заказа (если уже создан)
$orderId = !empty($_GET["ORDER_ID"]) ? (int)$_GET["ORDER_ID"] : (int)($arResult["ORDER_ID"] ?? 0);
$orderConfirmed = (($_GET["ORDER_CONFIRMED"] ?? $arResult["ORDER_CONFIRMED"] ?? "N") === "Y");

// В заказе есть позиция от поставщика и оформил не менеджер — order_create_handler.php
// (см. require_once выше) отложил отправку поставщику до оплаты и передал сюда
// флаг через redirect (см. ORDER_PAYMENT_HOLD_MINUTES). Показываем предупреждение
// с обратным отсчётом вместо обычного "Спасибо за заказ".
$paymentHold = ($_GET["PAYMENT_HOLD"] ?? "N") === "Y";
$paymentHoldMinutes = max(1, (int)($_GET["HOLD_MIN"] ?? (defined('ORDER_PAYMENT_HOLD_MINUTES') ? ORDER_PAYMENT_HOLD_MINUTES : 15)));
// Реальный дедлайн (b_supplier_order_payment_hold.DEADLINE), посчитанный БД при
// создании заказа — если он есть, отсчёт идёт от него, а не заново от момента
// показа страницы (иначе обновление страницы каждый раз давало бы полные 15 минут).
$paymentHoldDeadlineTs = (int)($_GET["DEADLINE"] ?? 0);
if ($paymentHoldDeadlineTs <= 0) {
    $paymentHoldDeadlineTs = time() + $paymentHoldMinutes * 60;
}
?>

<?php if ($orderConfirmed && $orderId > 0): ?>
    <!-- Заказ создан -->
    <div class="checkout-page">
        <h1 class="checkout-page__title">Заказ №<?= $orderId ?> оформлен</h1>
        <?php if ($paymentHold): ?>
        <div class="checkout-block payment-hold-notice" id="paymentHoldNotice" style="text-align:center;padding:48px 20px;">
            <div id="paymentHoldIconPending" style="font-size:48px;margin-bottom:16px;color:#e6a23c;"><svg class="icon"><use href="#icon-hourglass"></use></svg></div>
            <div id="paymentHoldIconCanceled" style="display:none;font-size:48px;margin-bottom:16px;color:var(--red);"><svg class="icon"><use href="#icon-x-circle"></use></svg></div>
            <div id="paymentHoldIconDispatched" style="display:none;font-size:48px;margin-bottom:16px;color:var(--green);"><svg class="icon"><use href="#icon-check-circle"></use></svg></div>

            <div id="paymentHoldStatePending">
                <h2 style="font-size:20px;margin-bottom:8px;">Заказ создан, требуется оплата</h2>
                <p style="color:var(--gray);margin-bottom:4px;max-width:480px;margin-left:auto;margin-right:auto;">В заказе есть позиции под заказ у поставщика — резерв действует ограниченное время.</p>
                <p style="color:var(--gray);margin-bottom:24px;">Оплатите заказ в течение <strong id="paymentHoldTimer" style="color:var(--black);">--:--</strong>, иначе он будет автоматически отменён.</p>
                <a href="/personal/orders/" class="btn btn--primary">Перейти к оплате</a>
            </div>

            <div id="paymentHoldStateChecking" style="display:none;">
                <h2 style="font-size:20px;margin-bottom:8px;">Проверяем статус оплаты…</h2>
                <p style="color:var(--gray);margin-bottom:24px;">Время на оплату истекло — уточняем, поступил ли платёж. Это займёт не больше минуты, страницу обновлять не нужно.</p>
            </div>

            <div id="paymentHoldStateCanceled" style="display:none;">
                <h2 style="font-size:20px;margin-bottom:8px;">Заказ отменён</h2>
                <p style="color:var(--gray);margin-bottom:24px;max-width:480px;margin-left:auto;margin-right:auto;">Оплата не поступила в отведённое время, резерв товара снят, и заказ был автоматически отменён. Оформите заказ заново, если он всё ещё нужен.</p>
                <a href="/cart/" class="btn btn--primary">Оформить заново</a>
            </div>

            <div id="paymentHoldStateDispatched" style="display:none;">
                <h2 style="font-size:20px;margin-bottom:8px;">Оплата получена</h2>
                <p style="color:var(--gray);margin-bottom:24px;">Заказ передан поставщику и уже в работе. Следить за статусом можно в истории заказов.</p>
                <a href="/personal/orders/" class="btn btn--primary">История заказов</a>
            </div>
        </div>
        <script>
        (function () {
            var orderId = <?= $orderId ?>;
            var deadline = <?= $paymentHoldDeadlineTs ?> * 1000;
            var timerEl = document.getElementById('paymentHoldTimer');
            var countdownTimer = null;
            var pollTimer = null;
            var pollAttempts = 0;
            var MAX_POLL_ATTEMPTS = 60; // до ~10 минут опроса после дедлайна на 1 попытку/10с — с запасом на задержку крона

            function showState(state) {
                ['Pending', 'Checking', 'Canceled', 'Dispatched'].forEach(function (s) {
                    var el = document.getElementById('paymentHoldState' + s);
                    if (el) el.style.display = (s === state) ? '' : 'none';
                });
                ['Pending', 'Canceled', 'Dispatched'].forEach(function (s) {
                    var el = document.getElementById('paymentHoldIcon' + s);
                    if (el) el.style.display = (s === state) ? '' : 'none';
                });
            }

            function tickCountdown() {
                var left = Math.max(0, deadline - Date.now());
                var m = Math.floor(left / 60000);
                var s = Math.floor((left % 60000) / 1000);
                if (timerEl) timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                if (left <= 0) {
                    clearInterval(countdownTimer);
                    showState('Checking');
                    startPolling();
                }
            }

            function pollStatus() {
                pollAttempts++;
                fetch('/local/ajax/order_payment_hold_status.php?ORDER_ID=' + orderId)
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.status === 'canceled') {
                            clearInterval(pollTimer);
                            showState('Canceled');
                        } else if (data.status === 'dispatched') {
                            clearInterval(pollTimer);
                            showState('Dispatched');
                        } else if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                            clearInterval(pollTimer);
                        }
                    })
                    .catch(function () {});
            }

            function startPolling() {
                pollStatus();
                pollTimer = setInterval(pollStatus, 10000);
            }

            if (deadline - Date.now() <= 0) {
                showState('Checking');
                startPolling();
            } else {
                tickCountdown();
                countdownTimer = setInterval(tickCountdown, 1000);
            }
        })();
        </script>
        <?php else: ?>
        <div class="checkout-block" style="text-align:center;padding:60px 20px;">
            <div style="font-size:48px;margin-bottom:16px;color:var(--green);"><svg class="icon"><use href="#icon-check-circle"></use></svg></div>
            <h2 style="font-size:20px;margin-bottom:8px;">Спасибо за заказ!</h2>
            <p style="color:var(--gray);margin-bottom:20px;">Мы свяжемся с вами в ближайшее время для подтверждения</p>
            <a href="/catalog/" class="btn btn--primary">Продолжить покупки</a>
        </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- Форма оформления -->
    <div class="checkout-page">
        <h1 class="checkout-page__title">Оформление заказа</h1>

        <?php if (!empty($orderConsentError)): ?>
        <div class="checkout-error">
            <svg class="icon"><use href="#icon-alert"></use></svg>
            Подтвердите, что вы ознакомлены с невозвратным товаром в заказе — без этого оформить заказ нельзя.
        </div>
        <?php endif; ?>

        <form name="ORDER_FORM" id="ORDER_FORM" method="post" action=""
              onsubmit="return validateForm()">

            <?= bitrix_sessid_post() ?>

            <div class="checkout-layout">
                <!-- Левая колонка -->
                <div class="checkout-form-col">

                    <!-- 1. Контакты -->
                    <div class="checkout-block">
                        <div class="checkout-block__title">
                            <span class="checkout-block__num">1</span> Контактные данные
                        </div>
                        <?php foreach ($userProps as $prop):
                            if ($prop['TYPE'] === 'LOCATION') continue;
                            $rawVal = (string)($prop['VALUE'] ?? '');
                            // Bitrix автозаполняет ФИО как "Имя Фамилия" (NAME + LAST_NAME);
                            // на сайте принят порядок "Фамилия Имя" — переставляем местами.
                            if (mb_strtoupper(trim($prop['NAME'])) === 'ФИО' && $rawVal !== '') {
                                $fioParts = preg_split('/\s+/', trim($rawVal));
                                if (count($fioParts) === 2) {
                                    $rawVal = $fioParts[1] . ' ' . $fioParts[0];
                                }
                            }
                            $val = htmlspecialchars($rawVal);
                            $type = in_array($prop['TYPE'], ['TEL','PHONE']) ? 'tel' :
                                    (in_array($prop['TYPE'], ['EMAIL']) ? 'email' : 'text');
                            $req = ($prop['REQUIED'] ?? '') === 'Y';
                        ?>
                        <div class="form-row">
                            <label><?= $prop['NAME'] ?><?= $req ? ' *' : '' ?></label>
                            <?php if ($prop['TYPE'] === 'TEXTAREA'): ?>
                                <textarea name="ORDER_PROP_<?= $prop['ID'] ?>"><?= $val ?></textarea>
                            <?php else: ?>
                                <input type="<?= $type ?>" name="ORDER_PROP_<?= $prop['ID'] ?>"
                                       value="<?= $val ?>" placeholder="<?= $prop['NAME'] ?>"
                                       <?= $req && !$val ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($userProps)): ?>
                        <div class="form-row">
                            <label>ФИО *</label>
                            <input type="text" name="ORDER_PROP_2" value="" placeholder="Иван Петров" required>
                        </div>
                        <div class="form-row">
                            <label>Телефон</label>
                            <input type="tel" name="ORDER_PROP_3" value="" placeholder="+7 (999) 123-45-67">
                        </div>
                        <div class="form-row">
                            <label>Email *</label>
                            <input type="email" name="ORDER_PROP_1" value="" placeholder="mail@example.com" required>
                        </div>
                        <div class="form-row">
                            <label>Город доставки *</label>
                            <input type="text" name="ORDER_PROP_4" value="" placeholder="Введите ваш город" required>
                        </div>
                        <div class="form-row">
                            <label>Адрес доставки</label>
                            <input type="text" name="ORDER_PROP_8" value="" placeholder="Улица, дом, корпус, квартира">
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Способ получения -->
                    <div class="checkout-block">
                        <div class="checkout-block__title">
                            <span class="checkout-block__num">2</span> Способ получения
                        </div>
                        <?php if (!empty($deliveries)):
                            // Разделяем службы доставки на "самовывоз" (несколько точек
                            // магазина, настроены в админке как отдельные службы) и
                            // "курьер" — по имени службы, других структурных признаков
                            // (тип/LOCATION) у этих служб нет.
                            $pickupDeliveries = [];
                            $courierDeliveries = [];
                            foreach ($deliveries as $did => $del) {
                                if (mb_stripos($del['NAME'], 'самовывоз') !== false) {
                                    $pickupDeliveries[$did] = $del;
                                } else {
                                    $courierDeliveries[$did] = $del;
                                }
                            }
                            $hasPickup = !empty($pickupDeliveries);
                            $hasCourier = !empty($courierDeliveries);
                            $pickupChecked = false;
                            foreach ($pickupDeliveries as $del) {
                                if (($del['CHECKED'] ?? '') === 'Y') { $pickupChecked = true; break; }
                            }
                            // Если ни одна служба самовывоза не отмечена явно, но курьер
                            // тоже не отмечен (первый заход в форму) — по умолчанию
                            // открываем самовывоз, если он вообще есть.
                            $activeMethod = $pickupChecked ? 'pickup' : ($hasCourier ? 'courier' : 'pickup');
                            $yandexMapsApiKey = function_exists('getYandexMapsApiKey') ? getYandexMapsApiKey() : '';
                            // Для геокодинга и ссылки на карту нужен чистый адрес, а не
                            // служебное имя вида "Самовывоз с Магазина (Елабуга, ...)" —
                            // если в имени есть скобки, берём текст внутри них.
                            $extractPickupAddress = function (array $del): string {
                                if (preg_match('/\(([^)]+)\)/', $del['NAME'], $m)) {
                                    return trim($m[1]);
                                }
                                return trim($del['NAME'] . ' ' . ($del['DESCRIPTION'] ?? ''));
                            };
                            // Точные координаты [широта, долгота] для известных точек
                            // самовывоза — проверены вручную, чтобы не расходовать
                            // геокодер на каждый показ карты. Для точки, которой здесь
                            // нет (например, добавят новый адрес в админке), ниже в JS
                            // остаётся рабочий фолбэк на ymaps.geocode().
                            $pickupKnownCoords = [
                                'РТ, Елабуга, пр-т Нефтяников 4' => [55.74767080512837, 52.00686362268443],
                                'Елабуга, ул. Баки Урманче 17а'  => [55.77622330421297, 52.02240121966068],
                            ];
                        ?>

                        <?php if ($hasPickup && $hasCourier): ?>
                        <div class="option-list receipt-method-list">
                            <label class="option-card <?= $activeMethod === 'pickup' ? 'option-card--active' : '' ?>">
                                <input type="radio" class="receipt-method-radio" name="RECEIPT_METHOD" value="pickup" data-panel="receipt-panel-pickup"
                                       <?= $activeMethod === 'pickup' ? 'checked' : '' ?>>
                                <div class="option-card__box">
                                    <div class="option-card__icon"><svg class="icon"><use href="#icon-store"></use></svg></div>
                                    <div class="option-card__info"><div class="option-card__title">Самовывоз</div></div>
                                </div>
                            </label>
                            <label class="option-card <?= $activeMethod === 'courier' ? 'option-card--active' : '' ?>">
                                <input type="radio" class="receipt-method-radio" name="RECEIPT_METHOD" value="courier" data-panel="receipt-panel-courier"
                                       <?= $activeMethod === 'courier' ? 'checked' : '' ?>>
                                <div class="option-card__box">
                                    <div class="option-card__icon"><svg class="icon"><use href="#icon-truck"></use></svg></div>
                                    <div class="option-card__info"><div class="option-card__title">Курьер</div></div>
                                </div>
                            </label>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasPickup): ?>
                        <div class="receipt-method-panel" id="receipt-panel-pickup" <?= ($hasCourier && $activeMethod !== 'pickup') ? 'style="display:none;"' : '' ?>>
                            <div class="option-list">
                                <?php foreach ($pickupDeliveries as $did => $del): ?>
                                <label class="option-card <?= ($del['CHECKED'] ?? '') === 'Y' ? 'option-card--active' : '' ?>">
                                    <input type="radio" name="DELIVERY_ID" value="<?= $del['ID'] ?>"
                                           <?= ($del['CHECKED'] ?? '') === 'Y' ? 'checked' : '' ?>>
                                    <div class="option-card__box">
                                        <div class="option-card__icon"><svg class="icon"><use href="#icon-pin"></use></svg></div>
                                        <div class="option-card__info">
                                            <div class="option-card__title"><?= $del['NAME'] ?></div>
                                            <?php if (!empty($del['DESCRIPTION'])): ?>
                                            <div class="option-card__desc"><?= $del['DESCRIPTION'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="option-card__price">
                                            <?= !empty($del['PRICE_FORMATTED']) ? $del['PRICE_FORMATTED'] : 'Бесплатно' ?>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($yandexMapsApiKey !== ''): ?>
                            <div class="pickup-map" id="pickup-map"
                                 data-points='<?= htmlspecialchars(json_encode(array_map(function ($del) use ($extractPickupAddress, $pickupKnownCoords) {
                                     $addr = $extractPickupAddress($del);
                                     return ['address' => $addr, 'coords' => $pickupKnownCoords[$addr] ?? null];
                                 }, array_values($pickupDeliveries))), ENT_QUOTES) ?>'></div>
                            <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= urlencode($yandexMapsApiKey) ?>&lang=ru_RU"></script>
                            <script>
                            (function () {
                                var mapEl = document.getElementById('pickup-map');
                                if (!mapEl || typeof ymaps === 'undefined') return;
                                var points = JSON.parse(mapEl.getAttribute('data-points') || '[]');
                                ymaps.ready(function () {
                                    var map = new ymaps.Map(mapEl, { center: [55.76, 52.05], zoom: 13 });
                                    // Экспонируем инстанс — если панель самовывоза при загрузке
                                    // страницы скрыта (активна "Курьер"), карта на скрытом
                                    // display:none контейнере инициализируется с нулевыми
                                    // размерами; при показе панели переключатель ниже вызывает
                                    // fitToViewport(), чтобы карта пересчитала размер и отрисовалась.
                                    window.pickupMapInstance = map;

                                    // Адреса самовывоза не меняются — геокодируем каждый
                                    // адрес максимум один раз на браузер и кэшируем координаты
                                    // в localStorage, чтобы повторные заходы в оформление заказа
                                    // не расходовали запросы к геокодеру повторно.
                                    var CACHE_KEY = 'pickupGeocodeCache';
                                    var cache = {};
                                    try { cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '{}'); } catch (e) {}

                                    function placeMark(address, coords) {
                                        map.geoObjects.add(new ymaps.Placemark(coords, { balloonContent: address }));
                                        return coords;
                                    }

                                    var geocodeQueue = points.map(function (point) {
                                        var address = point.address;
                                        // Известные координаты (проверены вручную) — без обращения к геокодеру.
                                        if (point.coords) {
                                            return Promise.resolve(placeMark(address, point.coords));
                                        }
                                        if (cache[address]) {
                                            return Promise.resolve(placeMark(address, cache[address]));
                                        }
                                        return ymaps.geocode(address).then(function (res) {
                                            var obj = res.geoObjects.get(0);
                                            if (!obj) return null;
                                            var coords = obj.geometry.getCoordinates();
                                            cache[address] = coords;
                                            try { localStorage.setItem(CACHE_KEY, JSON.stringify(cache)); } catch (e) {}
                                            return placeMark(address, coords);
                                        });
                                    });
                                    Promise.all(geocodeQueue).then(function (coordsList) {
                                        coordsList = coordsList.filter(Boolean);
                                        if (coordsList.length) map.setBounds(map.geoObjects.getBounds(), { checkZoomRange: true });
                                        if (mapEl.offsetParent !== null) map.container.fitToViewport();
                                    });
                                });
                            })();
                            </script>
                            <?php else: ?>
                            <div class="pickup-map-fallback">
                                <?php foreach ($pickupDeliveries as $del):
                                    $addr = $extractPickupAddress($del);
                                ?>
                                <a href="https://yandex.ru/maps/?text=<?= urlencode($addr) ?>" target="_blank" rel="noopener" class="pickup-map-fallback__link">
                                    <svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($del['NAME']) ?> — открыть на карте
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasCourier): ?>
                        <div class="receipt-method-panel" id="receipt-panel-courier" <?= ($hasPickup && $activeMethod !== 'courier') ? 'style="display:none;"' : '' ?>>
                            <div class="option-list">
                                <?php foreach ($courierDeliveries as $did => $del): ?>
                                <label class="option-card <?= ($del['CHECKED'] ?? '') === 'Y' ? 'option-card--active' : '' ?>">
                                    <input type="radio" name="DELIVERY_ID" value="<?= $del['ID'] ?>"
                                           <?= ($del['CHECKED'] ?? '') === 'Y' ? 'checked' : '' ?>>
                                    <div class="option-card__box">
                                        <div class="option-card__icon"><svg class="icon"><use href="#icon-truck"></use></svg></div>
                                        <div class="option-card__info">
                                            <div class="option-card__title"><?= $del['NAME'] ?></div>
                                            <?php if (!empty($del['DESCRIPTION'])): ?>
                                            <div class="option-card__desc"><?= $del['DESCRIPTION'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="option-card__price">
                                            <?= !empty($del['PRICE_FORMATTED']) ? $del['PRICE_FORMATTED'] : 'Бесплатно' ?>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                        <p class="checkout-hint">Заполните контакты для расчёта доставки</p>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Оплата -->
                    <div class="checkout-block">
                        <div class="checkout-block__title">
                            <span class="checkout-block__num">3</span> Оплата
                        </div>
                        <?php if (!empty($payments)): ?>
                        <div class="option-list">
                            <?php foreach ($payments as $pay): ?>
                            <label class="option-card <?= ($pay['CHECKED'] ?? '') === 'Y' ? 'option-card--active' : '' ?>">
                                <input type="radio" name="PAY_SYSTEM_ID" value="<?= $pay['ID'] ?>"
                                       <?= ($pay['CHECKED'] ?? '') === 'Y' ? 'checked' : '' ?>>
                                <div class="option-card__box">
                                    <div class="option-card__icon"><svg class="icon"><use href="#icon-card"></use></svg></div>
                                    <div class="option-card__info">
                                        <div class="option-card__title"><?= $pay['NAME'] ?></div>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="checkout-hint">Выберите доставку</p>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Комментарий -->
                    <div class="checkout-block">
                        <div class="checkout-block__title">
                            <span class="checkout-block__num">4</span> Комментарий
                        </div>
                        <div class="form-row">
                            <textarea name="ORDER_DESCRIPTION" rows="3"
                                      placeholder="Укажите детали..."><?= htmlspecialchars($arResult['USER_DESCRIPTION'] ?? '') ?></textarea>
                        </div>
                    </div>

                </div>

                <!-- Правая колонка -->
                <div class="checkout-sidebar">
                    <div class="checkout-summary">
                        <h3 class="checkout-summary__title">Ваш заказ</h3>

                        <?php if (!empty($basketItems)): ?>
                        <div class="checkout-basket">
                            <?php foreach ($basketItems as $bi):
                                $articleBrandParts = [];
                                if ($bi['BRAND'] !== '')   $articleBrandParts[] = 'Бренд: <b>' . htmlspecialchars($bi['BRAND']) . '</b>';
                                if ($bi['ARTICLE'] !== '') $articleBrandParts[] = 'Артикул: <b>' . htmlspecialchars($bi['ARTICLE']) . '</b>';
                                $articleBrandHtml = implode(' &middot; ', $articleBrandParts);
                            ?>
                            <div class="checkout-basket__item">
                                <div class="checkout-basket__img">
                                    <img src="<?= $bi['IMG'] ?>" alt="">
                                </div>
                                <div class="checkout-basket__info">
                                    <div class="checkout-basket__name"><?= htmlspecialchars($bi['NAME']) ?></div>
                                    <?php if ($articleBrandHtml !== ''): ?>
                                    <div class="checkout-basket__article"><?= $articleBrandHtml ?></div>
                                    <?php endif; ?>
                                    <div class="checkout-basket__meta">
                                        <?= $bi['QTY'] ?> шт. × <?= $bi['PRICE_FMT'] ?>
                                    </div>
                                    <?php if (!$bi['RETURNABLE']): ?>
                                    <div class="checkout-basket__no-return"><svg class="icon"><use href="#icon-x-circle"></use></svg> Без возврата</div>
                                    <?php endif; ?>
                                    <?php if ($isMgr && $bi['SUPPLIER_CODE'] !== ''): ?>
                                    <div class="checkout-basket__supplier">
                                        Поставщик: <?= htmlspecialchars($bi['SUPPLIER_LABEL']) ?><?php if (!empty($bi['WAREHOUSE'])): ?> &middot; Склад: <?= htmlspecialchars($bi['WAREHOUSE']) ?><?php endif; ?><?php if ($bi['DELIVERY_TEXT'] !== ''): ?> &middot; Доставка: <?= htmlspecialchars($bi['DELIVERY_TEXT']) ?><?php endif; ?>
                                    </div>
                                    <?php elseif ($bi['DELIVERY_TEXT'] !== ''): ?>
                                    <div class="checkout-basket__supplier">Доставка: <?= htmlspecialchars($bi['DELIVERY_TEXT']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="checkout-basket__price"><?= $bi['SUM_FMT'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="checkout-summary__rows">
                            <div class="checkout-summary__row">
                                <span>Товары (<?= $totalBasketQty ?> шт.)</span>
                                <span><?= $totalBasketFmt ?></span>
                            </div>
                            <div class="checkout-summary__row">
                                <span>Доставка</span>
                                <span>Уточняется</span>
                            </div>
                        </div>
                        <div class="checkout-summary__total">
                            <span>Итого</span>
                            <span><?= $totalBasketFmt ?></span>
                        </div>

                        <?php if ($hasNonReturnableItem): ?>
                        <div class="checkout-return-notice">
                            <svg class="icon"><use href="#icon-alert"></use></svg>
                            В заказе есть товар, который не подлежит возврату.
                        </div>
                        <label class="checkout-consent">
                            <input type="checkbox" name="agree_no_return" id="agree_no_return" value="Y">
                            Я уведомлен(а), что указанный товар не подлежит возврату, и согласен(на) с этим условием
                        </label>
                        <?php endif; ?>

                        <input type="hidden" name="confirmorder" value="Y">
                        <button type="submit" class="btn btn--primary btn--lg btn--block">
                            Оформить заказ
                        </button>
                        <p class="checkout-agreement">
                            Нажимая «Оформить заказ», вы соглашаетесь с условиями
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
.checkout-page, .checkout-page * { font-family: var(--font) !important; }
.checkout-page { max-width: 1240px; margin: 0 auto; padding: 30px 20px; }
.checkout-page__title { font-size: 28px; font-weight: 800; margin-bottom: 30px; color: var(--black); }
.checkout-layout { display: grid; grid-template-columns: 1fr 400px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .checkout-layout { grid-template-columns: 1fr; } }
.checkout-block {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 24px; margin-bottom: 12px;
    box-shadow: var(--shadow-sm);
}
.checkout-block__title {
    font-size: 16px; font-weight: 700; color: var(--black);
    text-transform: uppercase; letter-spacing: 0.03em;
    margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
}
.checkout-block__num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; background: var(--blue); color: #fff;
    border-radius: var(--radius); font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.form-row { margin-bottom: 14px; }
.form-row label {
    display: block; font-weight: 700; font-size: 12px; color: var(--black);
    text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 5px;
}
.form-row input[type="text"],
.form-row input[type="tel"],
.form-row input[type="email"],
.form-row textarea {
    width: 100%; padding: 11px 14px; border: 2px solid var(--border);
    border-radius: var(--radius); font-size: 14px;
    box-shadow: var(--shadow-sm); transition: border-color var(--transition);
    background: #fff; color: var(--black); box-sizing: border-box;
}
.form-row input:focus, .form-row textarea:focus {
    border-color: var(--blue); outline: none;
    box-shadow: 0 0 0 3px rgba(102,139,234,0.08);
}
.form-row textarea { resize: vertical; min-height: 70px; }

.option-list { display: flex; flex-direction: column; gap: 8px; }
.option-card { cursor: pointer; display: block; }
.option-card input[type="radio"] { display: none; }
.option-card__box {
    display: flex; align-items: center; gap: 12px; padding: 14px 16px;
    border: 2px solid var(--border); border-radius: var(--radius);
    background: var(--white); box-shadow: var(--shadow-sm);
    transition: all var(--transition);
}
.option-card__box:hover { border-color: #bbb; box-shadow: var(--shadow); }
.option-card--active .option-card__box,
.option-card input:checked + .option-card__box {
    border-color: var(--blue); background: rgba(102,139,234,0.04);
    box-shadow: 0 0 0 2px rgba(102,139,234,0.12);
}
.option-card__icon { font-size: 22px; flex-shrink: 0; }
.option-card__info { flex: 1; min-width: 0; }
.option-card__title { font-weight: 700; font-size: 14px; color: var(--black); }
.option-card__desc { font-size: 12px; color: var(--gray); margin-top: 2px; }
.option-card__price { font-weight: 800; font-size: 15px; flex-shrink: 0; color: var(--black); }
.checkout-hint { color: var(--gray); font-size: 13px; }

.checkout-sidebar { position: sticky; top: 20px; }
.checkout-summary {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);
}
.checkout-summary__title { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: var(--black); }
.checkout-basket { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; max-height: 320px; overflow-y: auto; }
.checkout-basket__item { display: flex; gap: 12px; align-items: center; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.checkout-basket__img {
    width: 48px; height: 48px; border-radius: var(--radius); overflow: hidden;
    background: #fafafa; border: 1px solid var(--border); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.checkout-basket__img img { max-width: 100%; max-height: 100%; object-fit: contain; }
.checkout-basket__info { flex: 1; min-width: 0; }
.checkout-basket__name { font-size: 12px; font-weight: 600; line-height: 1.3; color: var(--black); }
.checkout-basket__article { font-size: 11px; color: var(--gray); margin-top: 2px; }
.checkout-basket__meta { font-size: 11px; color: var(--gray-light); margin-top: 2px; }
.checkout-basket__supplier { font-size: 11px; color: var(--gray-light); margin-top: 2px; }
.checkout-basket__price { font-weight: 800; font-size: 13px; flex-shrink: 0; }
.checkout-summary__rows { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.checkout-summary__row { display: flex; justify-content: space-between; font-size: 13px; color: var(--gray); }
.checkout-summary__total {
    display: flex; justify-content: space-between; font-size: 18px; font-weight: 800;
    padding-top: 14px; border-top: 2px solid var(--border); margin-bottom: 18px;
    color: var(--black);
}
.checkout-agreement { font-size: 11px; color: var(--gray-light); text-align: center; margin-top: 10px; }

.checkout-error {
    display: flex; align-items: center; gap: 10px; background: #fdecec; color: var(--red);
    border-radius: var(--radius); padding: 12px 16px; font-size: 13px; margin-bottom: 16px;
}
.checkout-error .icon { width: 18px; height: 18px; flex-shrink: 0; }

.checkout-basket__no-return {
    display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700;
    color: var(--red); margin-top: 3px;
}
.checkout-basket__no-return .icon { width: 12px; height: 12px; }

.checkout-return-notice {
    display: flex; align-items: center; gap: 8px; background: #fff5e6; color: #8a5300;
    border-radius: var(--radius); padding: 10px 14px; font-size: 12px; margin-bottom: 10px;
}
.checkout-return-notice .icon { width: 16px; height: 16px; flex-shrink: 0; }

.checkout-consent {
    display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: var(--black);
    margin-bottom: 14px; cursor: pointer; line-height: 1.4;
}
.checkout-consent input { margin-top: 2px; flex-shrink: 0; }

.receipt-method-list { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.receipt-method-panel { margin-top: 4px; }
.pickup-map { width: 100%; height: 260px; border-radius: var(--radius); overflow: hidden; margin-top: 12px; border: 1px solid var(--border); }
.pickup-map-fallback { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.pickup-map-fallback__link {
    display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--blue);
    padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); background: #fafbff;
}
.pickup-map-fallback__link:hover { border-color: var(--blue); color: var(--blue-dark); }
.pickup-map-fallback__link .icon { width: 16px; height: 16px; flex-shrink: 0; }

.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; border-radius: var(--radius); cursor: pointer; text-decoration: none; border: 1px solid transparent; transition: all var(--transition); line-height: 1.2; }
.btn--primary { background: var(--blue); color: #fff; border-color: var(--blue); box-shadow: 0 1px 3px rgba(102,139,234,0.3); padding: 14px 24px; font-size: 14px; }
.btn--primary:hover { background: var(--blue-dark); border-color: var(--blue-dark); color: #fff; }
.btn--lg { padding: 14px 32px; font-size: 16px; }
.btn--block { display: flex; width: 100%; }
</style>

<script>
// Подсветка опций
document.querySelectorAll('.option-card input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var list = this.closest('.option-list');
        if (list) {
            list.querySelectorAll('.option-card').forEach(function(c) { c.classList.remove('option-card--active'); });
        }
        if (this.checked) {
            var card = this.closest('.option-card');
            if (card) card.classList.add('option-card--active');
        }
    });
    if (radio.checked) {
        var card = radio.closest('.option-card');
        if (card) card.classList.add('option-card--active');
    }
});

// Переключение "Самовывоз" / "Курьер" — показываем нужную панель и следим,
// чтобы в скрытой панели не оставался выбранным DELIVERY_ID от другого способа.
document.querySelectorAll('.receipt-method-radio').forEach(function (radio) {
    radio.addEventListener('change', function () {
        if (!this.checked) return;
        document.querySelectorAll('.receipt-method-panel').forEach(function (panel) {
            panel.style.display = 'none';
        });
        var panel = document.getElementById(this.getAttribute('data-panel'));
        if (!panel) return;
        panel.style.display = '';
        // Карта самовывоза могла инициализироваться на скрытом контейнере
        // (нулевые размеры) — пересчитываем размер теперь, когда панель видна.
        if (panel.id === 'receipt-panel-pickup' && window.pickupMapInstance) {
            window.pickupMapInstance.container.fitToViewport();
        }
        var checkedInPanel = panel.querySelector('input[name="DELIVERY_ID"]:checked');
        if (!checkedInPanel) {
            var firstRadio = panel.querySelector('input[name="DELIVERY_ID"]');
            if (firstRadio) {
                firstRadio.checked = true;
                firstRadio.dispatchEvent(new Event('change'));
            }
        }
    });
});

function validateForm() {
    var phone = document.querySelector('input[type="tel"][required]');
    if (phone && !phone.value.trim()) {
        alert('Пожалуйста, укажите телефон');
        phone.focus();
        return false;
    }
    var agreeNoReturn = document.getElementById('agree_no_return');
    if (agreeNoReturn && !agreeNoReturn.checked) {
        alert('Подтвердите, что вы ознакомлены с невозвратным товаром в заказе');
        agreeNoReturn.focus();
        return false;
    }
    return true;
}
</script>
