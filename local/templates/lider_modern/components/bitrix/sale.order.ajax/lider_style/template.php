<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CModule::IncludeModule('sale');
CModule::IncludeModule('iblock');
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/order_create_handler.php");

// Товары из корзины
$basketItems = [];
$bRes = CSaleBasket::GetList(['NAME' => 'ASC'], [
    'FUSER_ID' => CSaleBasket::GetBasketUserID(),
    'ORDER_ID' => 'NULL',
    'LID' => SITE_ID
]);
$totalBasket = 0; $totalBasketQty = 0;
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
?>

<?php if ($orderConfirmed && $orderId > 0): ?>
    <!-- Заказ создан -->
    <div class="checkout-page">
        <h1 class="checkout-page__title">Заказ №<?= $orderId ?> оформлен</h1>
        <?php if ($paymentHold): ?>
        <div class="checkout-block payment-hold-notice" style="text-align:center;padding:48px 20px;">
            <div style="font-size:48px;margin-bottom:16px;color:#e6a23c;"><svg class="icon"><use href="#icon-hourglass"></use></svg></div>
            <h2 style="font-size:20px;margin-bottom:8px;">Заказ создан, требуется оплата</h2>
            <p style="color:var(--gray);margin-bottom:4px;max-width:480px;margin-left:auto;margin-right:auto;">В заказе есть позиции под заказ у поставщика — резерв действует ограниченное время.</p>
            <p style="color:var(--gray);margin-bottom:24px;">Оплатите заказ в течение <strong id="paymentHoldTimer" style="color:var(--black);">--:--</strong>, иначе он будет автоматически отменён.</p>
            <a href="/personal/orders/" class="btn btn--primary">Перейти к оплате</a>
            <script>
            (function () {
                var deadline = Date.now() + <?= $paymentHoldMinutes ?> * 60 * 1000;
                var el = document.getElementById('paymentHoldTimer');
                var timer = null;
                function tick() {
                    var left = Math.max(0, deadline - Date.now());
                    var m = Math.floor(left / 60000);
                    var s = Math.floor((left % 60000) / 1000);
                    el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                    if (left <= 0 && timer) {
                        clearInterval(timer);
                        el.textContent = '0:00';
                    }
                }
                tick();
                timer = setInterval(tick, 1000);
            })();
            </script>
        </div>
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
                            $val = htmlspecialchars($prop['VALUE'] ?? '');
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

                    <!-- 2. Доставка -->
                    <div class="checkout-block">
                        <div class="checkout-block__title">
                            <span class="checkout-block__num">2</span> Доставка
                        </div>
                        <?php if (!empty($deliveries)): ?>
                        <div class="option-list">
                            <?php foreach ($deliveries as $did => $del): ?>
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
                            <?php foreach ($basketItems as $bi): ?>
                            <div class="checkout-basket__item">
                                <div class="checkout-basket__img">
                                    <img src="<?= $bi['IMG'] ?>" alt="">
                                </div>
                                <div class="checkout-basket__info">
                                    <div class="checkout-basket__name"><?= htmlspecialchars($bi['NAME']) ?></div>
                                    <div class="checkout-basket__meta">
                                        <?= $bi['QTY'] ?> шт. × <?= $bi['PRICE_FMT'] ?>
                                    </div>
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
.checkout-basket__meta { font-size: 11px; color: var(--gray-light); margin-top: 2px; }
.checkout-basket__price { font-weight: 800; font-size: 13px; flex-shrink: 0; }
.checkout-summary__rows { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.checkout-summary__row { display: flex; justify-content: space-between; font-size: 13px; color: var(--gray); }
.checkout-summary__total {
    display: flex; justify-content: space-between; font-size: 18px; font-weight: 800;
    padding-top: 14px; border-top: 2px solid var(--border); margin-bottom: 18px;
    color: var(--black);
}
.checkout-agreement { font-size: 11px; color: var(--gray-light); text-align: center; margin-top: 10px; }

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

function validateForm() {
    var phone = document.querySelector('input[type="tel"][required]');
    if (phone && !phone.value.trim()) {
        alert('Пожалуйста, укажите телефон');
        phone.focus();
        return false;
    }
    return true;
}
</script>
