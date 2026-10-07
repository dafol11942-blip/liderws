<?php
/**
 * Статус заказа "Оплата поступила, заказ обрабатывается" (PA) и почтовый
 * шаблон к нему. Ставится модулем Альфа-Банка (rbs.payment) после успешной
 * оплаты: Настройки модулей → "Оплата картой банка" → Callback-уведомления →
 * "Оплачен (deposited)" → статус [PA].
 *
 * Идемпотентный — повторный запуск ничего не дублирует, только обновляет
 * название статуса и текст письма.
 *
 * Запуск: php local/scripts/add_paid_status.php
 */
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('sale');

const PAID_STATUS_ID = 'PA';
const PAID_STATUS_NAME = 'Оплата поступила, заказ обрабатывается';
const PAID_STATUS_DESCRIPTION = 'Онлайн-оплата получена, заказ передан в обработку';

// ── Статус ─────────────────────────────────────────────
$statusFields = [
    'TYPE' => 'O',
    'SORT' => 150, // после N "Принят, ожидается оплата" (100), до P/S
    'NOTIFY' => 'Y',
    'LANG' => [
        ['LID' => 'ru', 'NAME' => PAID_STATUS_NAME, 'DESCRIPTION' => PAID_STATUS_DESCRIPTION],
        ['LID' => 'en', 'NAME' => 'Payment received, order is being processed', 'DESCRIPTION' => 'Online payment received'],
    ],
];

if (CSaleStatus::GetByID(PAID_STATUS_ID)) {
    CSaleStatus::Update(PAID_STATUS_ID, $statusFields);
    echo "Статус " . PAID_STATUS_ID . " уже был — название обновлено\n";
} else {
    // Тип почтового события и шаблон заводим ниже сами — если ядро уже создало
    // типовой шаблон SALE_STATUS_CHANGED_PA, его текст просто заменится нашим.
    $added = CSaleStatus::Add(['ID' => PAID_STATUS_ID] + $statusFields);
    if (!$added) {
        global $APPLICATION;
        $ex = $APPLICATION->GetException();
        echo "Не удалось создать статус: " . ($ex ? $ex->GetString() : 'неизвестная ошибка') . "\n";
        exit(1);
    }
    echo "Статус " . PAID_STATUS_ID . " создан\n";
}

// ── Тип почтового события ──────────────────────────────
$eventName = 'SALE_STATUS_CHANGED_' . PAID_STATUS_ID;
$eventFieldsDescription = implode("\n", [
    '#ORDER_ID# - код заказа',
    '#ORDER_ACCOUNT_NUMBER_ENCODE# - номер заказа (для ссылок)',
    '#ORDER_REAL_ID# - реальный ID заказа',
    '#ORDER_DATE# - дата заказа',
    '#ORDER_STATUS# - статус заказа',
    '#EMAIL# - E-Mail покупателя',
    '#ORDER_DESCRIPTION# - описание статуса заказа',
    '#TEXT# - текст',
    '#SALE_EMAIL# - E-Mail отдела продаж',
    '#ORDER_PUBLIC_URL# - ссылка на заказ без авторизации',
]);
foreach (['ru' => 'Изменение статуса заказа на "' . PAID_STATUS_NAME . '"', 'en' => 'Order status changed to "Payment received"'] as $lid => $typeName) {
    $existing = CEventType::GetList(['TYPE_ID' => $eventName, 'LID' => $lid])->Fetch();
    if (!$existing) {
        (new CEventType)->Add([
            'LID' => $lid,
            'EVENT_NAME' => $eventName,
            'NAME' => $typeName,
            'DESCRIPTION' => $eventFieldsDescription,
        ]);
        echo "Тип почтового события {$eventName} ({$lid}) создан\n";
    }
}

// ── Почтовый шаблон ────────────────────────────────────
$subject = '#SITE_NAME#: оплата по заказу №#ORDER_ID# получена';
$message = <<<'HTML'
<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1d1d1f;max-width:600px;margin:0 auto;">
    <div style="padding:24px 28px;border:1px solid #e5e7ef;border-radius:16px;">
        <h2 style="margin:0 0 16px;font-size:20px;">Оплата получена — спасибо!</h2>
        <p style="margin:0 0 12px;">Здравствуйте!</p>
        <p style="margin:0 0 12px;">Мы получили оплату по заказу <b>№#ORDER_ID#</b> от #ORDER_DATE#. Заказ передан в обработку.</p>
        <p style="margin:0 0 20px;">Текущий статус: <b style="color:#668bea;">#ORDER_STATUS#</b></p>
        <p style="margin:0 0 20px;">Когда заказ будет готов к выдаче, мы сообщим вам дополнительно.</p>
        <p style="margin:0 0 24px;">
            <a href="https://#SERVER_NAME#/personal/orders/" style="display:inline-block;padding:12px 24px;background:#668bea;color:#ffffff;text-decoration:none;border-radius:12px;font-weight:bold;">Мои заказы</a>
        </p>
        <p style="margin:0;font-size:13px;color:#8a8f98;">Если у вас есть вопросы, просто ответьте на это письмо.<br>#SITE_NAME# — #SERVER_NAME#</p>
    </div>
</div>
HTML;

$sites = [];
$siteRes = CSite::GetList('sort', 'asc', ['ACTIVE' => 'Y']);
while ($site = $siteRes->Fetch()) {
    $sites[] = $site['LID'];
}

$messageFields = [
    'ACTIVE' => 'Y',
    'EVENT_NAME' => $eventName,
    'LID' => $sites,
    'EMAIL_FROM' => '#SALE_EMAIL#',
    'EMAIL_TO' => '#EMAIL#',
    'BCC' => '',
    'SUBJECT' => $subject,
    'BODY_TYPE' => 'html',
    'MESSAGE' => $message,
];

$eventMessage = new CEventMessage;
$existingMessage = CEventMessage::GetList('id', 'asc', ['TYPE_ID' => $eventName])->Fetch();
if ($existingMessage) {
    $ok = $eventMessage->Update($existingMessage['ID'], $messageFields);
    echo $ok ? "Почтовый шаблон #{$existingMessage['ID']} обновлён\n" : "Ошибка обновления шаблона: {$eventMessage->LAST_ERROR}\n";
} else {
    $newId = $eventMessage->Add($messageFields);
    echo $newId ? "Почтовый шаблон #{$newId} создан\n" : "Ошибка создания шаблона: {$eventMessage->LAST_ERROR}\n";
}

echo "Готово. Теперь выберите статус [" . PAID_STATUS_ID . "] для \"Оплачен (deposited)\" в настройках модуля rbs.payment.\n";
