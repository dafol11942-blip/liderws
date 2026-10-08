<?php
/**
 * Платёжная система "Без оплаты (оформляет менеджер)" — видна и доступна
 * только группе менеджеров (isManager()), работает в обход правил:
 *   - для заказного товара от поставщика (обычному покупателю там доступна
 *     только оплата картой);
 *   - без 15-минутного окна оплаты.
 * Распознаётся по символьному коду MANAGER_PAY_SYSTEM_CODE
 * (local/php_interface/include/order_actions.php). Обработчик — штатный
 * "Наличные" (cash): никакого онлайн-платежа, заказ просто оформляется.
 *
 * Идемпотентный — повторный запуск обновит название/описание, не создаст дубль.
 *
 * Запуск: php local/scripts/add_manager_pay_system.php
 */
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('sale');

use Bitrix\Sale\PaySystem\Manager;

$fields = [
    'NAME' => 'Без оплаты (оформляет менеджер)',
    'PSA_NAME' => 'Без оплаты (оформляет менеджер)',
    'DESCRIPTION' => 'Служебный способ оплаты для менеджеров: заказ оформляется без онлайн-оплаты и без ограничений для заказного товара. Покупателям не показывается.',
    'CODE' => MANAGER_PAY_SYSTEM_CODE,
    'ACTION_FILE' => 'cash',
    'ACTIVE' => 'Y',
    'SORT' => 900,
    'NEW_WINDOW' => 'N',
    'IS_CASH' => 'N',
    'ALLOW_EDIT_PAYMENT' => 'Y',
    'ENTITY_REGISTRY_TYPE' => 'ORDER',
];

$existing = Manager::getList([
    'filter' => ['=CODE' => MANAGER_PAY_SYSTEM_CODE],
    'select' => ['ID'],
])->fetch();

if ($existing) {
    $id = (int)$existing['ID'];
    $result = Manager::update($id, $fields);
    if (!$result->isSuccess()) {
        echo "Ошибка обновления: " . implode('; ', $result->getErrorMessages()) . "\n";
        exit(1);
    }
    echo "Платёжная система #{$id} уже была — обновлена\n";
} else {
    $result = Manager::add($fields);
    if (!$result->isSuccess()) {
        echo "Ошибка создания: " . implode('; ', $result->getErrorMessages()) . "\n";
        exit(1);
    }
    $id = (int)$result->getId();
    // Так же делает админка после создания: PAY_SYSTEM_ID = собственный ID.
    Manager::update($id, ['PAY_SYSTEM_ID' => $id]);
    echo "Платёжная система #{$id} создана\n";
}

echo "Готово. Проверьте в админке (Магазин → Платёжные системы), что у неё нет\n"
   . "ограничений, скрывающих её для нужных служб доставки и типов плательщиков.\n";
