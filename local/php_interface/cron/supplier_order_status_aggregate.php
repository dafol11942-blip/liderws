<?php
/**
 * Крон: пересчитывает общий статус заказа по этапам (STAGE) его позиций у
 * поставщиков и, если можно, переводит заказ в Bitrix-статус, отражающий этот
 * этап. Запускать ПОСЛЕ supplier_order_status_poll.php (тот обновляет STAGE
 * по каждой позиции; этот — агрегирует и меняет статус САМОГО заказа).
 *
 * Вынесен в отдельный скрипт от supplier_order_status_poll.php намеренно:
 * смена статуса заказа требует полного Bitrix (\Bitrix\Sale\Order::load()/
 * save() — единственный безопасный способ, чтобы не пропустить уведомления/
 * историю/события, завязанные на смену статуса), а supplier_order_status_poll.php
 * специально лёгкий (голый mysqli, без полной загрузки ядра) и уже проверен
 * вживую — рисковать им, добавляя туда тяжёлый бутстрап, не нужно.
 *
 * Этапы: ordered < in_transit < ready, плюс отдельно refused.
 * Правило агрегации:
 *   - если ВСЕ позиции заказа refused → агрегат = refused, статус SX, И заказ
 *     помечается отменённым (нативное поле CANCELED='Y' + REASON_CANCELED —
 *     тот же признак, что использует автоотмена по неоплате, см. план "Оплата
 *     в течение 15 минут"): полный отказ поставщика по всем позициям — это
 *     фактическая отмена заказа, а не просто статус для сведения;
 *   - иначе — самый ранний этап среди позиций, которые НЕ refused (частичный
 *     отказ одной позиции не топит весь заказ — виден только на её уровне).
 * Заказ трогаем только если его текущий STATUS_ID — один из "наших
 * управляемых" (N, S, SO, ST, SR, SX) — ручной перевод менеджера в любой
 * другой статус (F, DN, DF и т.п.) автоматика не переопределяет. SX включён в
 * управляемые, чтобы самовосстанавливаться: заказ, у которого статус уже SX,
 * но CANCELED почему-то не выставлен (например, создан до этого изменения),
 * дозаполняется при следующем проходе, а не только при первом переходе в SX.
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$logFile = $docRoot . '/upload/logs/supplier_order_status_aggregate_' . date('Y-m-d') . '.log';

function clog(string $msg): void {
    global $logFile;
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

// Бутстрап — самое первое, что делает скрипт, ДО любого echo/clog(). prolog_before.php
// сам стартует сессию — если до него уже была выведена хоть одна вписанная в поток
// строка (например, наш собственный лог через echo), PHP считает "заголовки уже
// отправлены" и session_start() падает, даже в CLI. ob_start() — дополнительная
// подстраховка на случай, если сам прolog что-то выведет раньше session_start().
ob_start();
try {
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    define('NO_KEEP_STATISTIC', true);
    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
    CModule::IncludeModule('sale');
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    file_put_contents($logFile, '[' . date('H:i:s') . '] Bitrix bootstrap failed: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    exit(1);
}

const STAGE_ORDER = ['ordered' => 1, 'in_transit' => 2, 'ready' => 3];
const STAGE_TO_STATUS = [
    'ordered'    => 'SO',
    'in_transit' => 'ST',
    'ready'      => 'SR',
    'refused'    => 'SX',
];
const MANAGED_STATUSES = ['N', 'S', 'SO', 'ST', 'SR', 'SX'];

clog('=== supplier_order_status_aggregate START ===');

try {
    $db = \Bitrix\Main\Application::getConnection();

    // Заказы, у которых есть отслеживаемые позиции у поставщиков и текущий
    // статус — один из управляемых автоматикой.
    $orderRows = $db->query(
        "SELECT DISTINCT so.ORDER_ID, o.STATUS_ID, o.CANCELED
         FROM b_supplier_order so
         JOIN b_sale_order o ON o.ID = so.ORDER_ID
         WHERE o.STATUS_ID IN ('" . implode("','", MANAGED_STATUSES) . "')"
    )->fetchAll();
} catch (\Throwable $e) {
    clog('SELECT orders failed: ' . $e->getMessage());
    exit(1);
}

$checked = 0;
$changed = 0;

foreach ($orderRows as $orderRow) {
    $orderId = (int)$orderRow['ORDER_ID'];
    $currentStatus = (string)$orderRow['STATUS_ID'];
    $alreadyCanceled = ((string)($orderRow['CANCELED'] ?? 'N')) === 'Y';
    $checked++;

    try {
        $stageRows = $db->query(
            "SELECT i.STAGE
             FROM b_supplier_order_item i
             JOIN b_supplier_order so ON so.ID = i.SUPPLIER_ORDER_ID
             WHERE so.ORDER_ID = " . $orderId
        )->fetchAll();
    } catch (\Throwable $e) {
        clog("Заказ №{$orderId}: SELECT позиций упал — " . $e->getMessage());
        continue;
    }

    if (empty($stageRows)) continue;

    $stages = array_column($stageRows, 'STAGE');
    $nonRefused = array_filter($stages, fn($s) => $s !== 'refused');

    if (empty($nonRefused)) {
        $aggregateStage = 'refused';
    } else {
        // Самый ранний (минимальный) этап среди неотказанных позиций.
        $aggregateStage = array_reduce($nonRefused, function ($carry, $s) {
            $rank = STAGE_ORDER[$s] ?? STAGE_ORDER['ordered'];
            $carryRank = STAGE_ORDER[$carry] ?? STAGE_ORDER['ordered'];
            return $rank < $carryRank ? $s : $carry;
        }, 'ready');
    }

    $newStatus = STAGE_TO_STATUS[$aggregateStage] ?? null;
    $needsStatusChange = $newStatus && $newStatus !== $currentStatus;
    $needsCancel = $aggregateStage === 'refused' && !$alreadyCanceled;

    if (!$needsStatusChange && !$needsCancel) continue;

    try {
        $order = \Bitrix\Sale\Order::load($orderId);
        if (!$order) {
            clog("Заказ №{$orderId}: не найден при загрузке");
            continue;
        }
        if ($needsStatusChange) {
            $order->setField('STATUS_ID', $newStatus);
        }
        if ($needsCancel) {
            $order->setField('CANCELED', 'Y');
            $order->setField('REASON_CANCELED', 'Поставщик отказал по всем позициям заказа');
        }
        $saveResult = $order->save();
        if ($saveResult->isSuccess()) {
            $changed++;
            $what = [];
            if ($needsStatusChange) $what[] = "{$currentStatus} → {$newStatus}";
            if ($needsCancel) $what[] = 'CANCELED=Y';
            clog("Заказ №{$orderId}: " . implode(', ', $what) . " (агрегат позиций: {$aggregateStage})");
        } else {
            clog("Заказ №{$orderId}: не удалось сохранить — " . implode('; ', $saveResult->getErrorMessages()));
        }
    } catch (\Throwable $e) {
        clog("Заказ №{$orderId}: смена статуса упала — " . $e->getMessage());
    }
}

clog("Проверено заказов: {$checked}, изменено: {$changed}");
clog('=== supplier_order_status_aggregate DONE ===');
