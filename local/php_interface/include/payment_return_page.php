<?php
/**
 * Общая разметка страниц возврата из банка после онлайн-оплаты:
 * /order/payment-success/ и /order/payment-fail/ (адреса указываются в
 * настройках платёжной системы Альфа-Банка: "Адрес ... в случае успешной /
 * неуспешной оплаты").
 *
 * Банк (модуль rbs.payment) возвращает покупателя БЕЗ номера заказа, поэтому
 * заказ берём из сессии: LIDER_PAY_ORDER_ID запоминается в
 * getOrderOnlinePayForms() в момент показа кнопки "Оплатить".
 *
 * Перед подключением задать $paymentReturnMode = 'success' | 'fail'.
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

CModule::IncludeModule('sale');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php'); // formatRub(), isManager()
global $USER;

$paymentReturnMode = ($paymentReturnMode ?? 'success') === 'fail' ? 'fail' : 'success';

$returnOrder = null;
$returnOrderId = (int)($_SESSION['LIDER_PAY_ORDER_ID'] ?? 0);
if ($returnOrderId > 0) {
    try {
        $returnOrder = \Bitrix\Sale\Order::load($returnOrderId);
    } catch (\Throwable $e) {
        $returnOrder = null;
    }
    // Заказ из сессии — значит, этот же посетитель только что видел его кнопку
    // оплаты; для авторизованного дополнительно сверяем владельца.
    if ($returnOrder && $USER->IsAuthorized() && (int)$returnOrder->getUserId() !== (int)$USER->GetID()
        && !(function_exists('isManager') && isManager())) {
        $returnOrder = null;
    }
}

$isPaid = $returnOrder ? orderHasPaidPayment($returnOrder) : false;
$isCanceled = $returnOrder && $returnOrder->getField('CANCELED') === 'Y';
$orderNumber = $returnOrder ? (string)$returnOrder->getField('ACCOUNT_NUMBER') : '';
$orderSum = $returnOrder ? formatRub((float)$returnOrder->getPrice()) : '';
$ordersUrl = $USER->IsAuthorized() ? '/personal/orders/' . ($returnOrder ? '?ID=' . (int)$returnOrder->getId() : '') : '/';

// Банк иногда возвращает покупателя раньше, чем дошло уведомление об оплате
// (callback) — на странице успеха без отметки об оплате ждём и обновляем.
$waitingForCallback = $paymentReturnMode === 'success' && $returnOrder && !$isPaid && !$isCanceled;
// На странице неудачи — сразу даём оплатить ещё раз.
$retryForms = ($paymentReturnMode === 'fail' && $returnOrder && !$isPaid && !$isCanceled)
    ? getOrderOnlinePayForms($returnOrder) : [];
?>

<div class="pay-return">
    <div class="pay-return__card">
        <?php if ($paymentReturnMode === 'success' && ($isPaid || !$returnOrder)): ?>
            <div class="pay-return__icon pay-return__icon--ok"><svg class="icon"><use href="#icon-check-circle"></use></svg></div>
            <h1 class="pay-return__title">Оплата прошла успешно</h1>
            <?php if ($returnOrder): ?>
            <p class="pay-return__text">Заказ <b>№<?= htmlspecialchars($orderNumber) ?></b> на сумму <b><?= $orderSum ?></b> оплачен и передан в обработку.</p>
            <?php else: ?>
            <p class="pay-return__text">Спасибо! Мы получили вашу оплату и уже обрабатываем заказ.</p>
            <?php endif; ?>
            <p class="pay-return__hint">Мы сообщим, когда заказ будет готов к выдаче.</p>

        <?php elseif ($paymentReturnMode === 'success'): ?>
            <div class="pay-return__icon pay-return__icon--wait"><svg class="icon"><use href="#icon-hourglass"></use></svg></div>
            <h1 class="pay-return__title">Проверяем оплату…</h1>
            <p class="pay-return__text">Банк подтверждает платёж по заказу <b>№<?= htmlspecialchars($orderNumber) ?></b> на сумму <b><?= $orderSum ?></b>. Обычно это занимает несколько секунд — страница обновится сама.</p>
            <p class="pay-return__hint" id="payReturnSlow" style="display:none;">Подтверждение задерживается. Если деньги списаны — не переживайте, статус заказа обновится автоматически.</p>

        <?php else: ?>
            <div class="pay-return__icon pay-return__icon--fail"><svg class="icon"><use href="#icon-x-circle"></use></svg></div>
            <h1 class="pay-return__title">Оплата не прошла</h1>
            <?php if ($isPaid): ?>
            <p class="pay-return__text">Заказ <b>№<?= htmlspecialchars($orderNumber) ?></b> уже оплачен — повторная оплата не нужна.</p>
            <?php elseif ($isCanceled): ?>
            <p class="pay-return__text">Заказ <b>№<?= htmlspecialchars($orderNumber) ?></b> отменён. Оформите заказ заново, если он всё ещё нужен.</p>
            <?php else: ?>
            <p class="pay-return__text">Платёж<?= $returnOrder ? ' по заказу <b>№' . htmlspecialchars($orderNumber) . '</b>' : '' ?> был отклонён или отменён. Деньги не списаны. Попробуйте ещё раз или выберите другую карту.</p>
            <?php endif; ?>
            <?php if ($retryForms): ?>
            <div class="pay-return__retry"><?php renderOrderOnlinePayForms($retryForms); ?></div>
            <?php endif; ?>
            <p class="pay-return__hint">Если не получается оплатить — позвоните нам, поможем оформить оплату другим способом.</p>
        <?php endif; ?>

        <div class="pay-return__actions">
            <a href="<?= htmlspecialcharsbx($ordersUrl) ?>" class="btn btn--secondary"><?= $USER->IsAuthorized() ? ($returnOrder ? 'К заказу' : 'Мои заказы') : 'На главную' ?></a>
            <a href="/catalog/" class="btn btn--primary">Продолжить покупки</a>
        </div>
    </div>
</div>

<?php if ($waitingForCallback): ?>
<script>
(function () {
    // До ~1 минуты перезагружаем страницу, пока не придёт подтверждение оплаты.
    var key = 'payReturnTries', tries = 0;
    try { tries = parseInt(sessionStorage.getItem(key) || '0', 10) || 0; } catch (e) {}
    if (tries >= 12) {
        var slow = document.getElementById('payReturnSlow');
        if (slow) slow.style.display = '';
        try { sessionStorage.removeItem(key); } catch (e) {}
        return;
    }
    try { sessionStorage.setItem(key, String(tries + 1)); } catch (e) {}
    setTimeout(function () { location.reload(); }, 5000);
})();
</script>
<?php else: ?>
<script>try { sessionStorage.removeItem('payReturnTries'); } catch (e) {}</script>
<?php endif; ?>

<style>
.pay-return { max-width: 760px; margin: 0 auto; padding: 40px 20px 60px; }
.pay-return__card {
    background: var(--white, #fff); border: 1px solid var(--border); border-radius: 24px;
    box-shadow: var(--shadow-sm); padding: 48px 32px; text-align: center;
}
.pay-return__icon { font-size: 56px; line-height: 1; margin-bottom: 16px; }
.pay-return__icon--ok { color: var(--green); }
.pay-return__icon--wait { color: #e6a23c; }
.pay-return__icon--fail { color: var(--red); }
.pay-return__title { font-size: 24px; font-weight: 800; color: var(--black); margin: 0 0 10px; }
.pay-return__text { font-size: 15px; color: var(--black); margin: 0 auto 8px; max-width: 520px; line-height: 1.5; }
.pay-return__hint { font-size: 13px; color: var(--gray); margin: 8px auto 0; max-width: 520px; line-height: 1.5; }
.pay-return__retry { margin-top: 20px; }
.pay-return__retry .confirm-pay { margin-bottom: 8px; }
.pay-return__actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
@media (max-width: 600px) {
    .pay-return__card { padding: 32px 18px; border-radius: 18px; }
    .pay-return__title { font-size: 20px; }
}
</style>
