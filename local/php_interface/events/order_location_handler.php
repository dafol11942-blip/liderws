<?php
/**
 * Свойство заказа типа LOCATION (город доставки) сознательно скрыто от
 * покупателя в форме оформления (см. TYPE === 'LOCATION' в
 * local/templates/lider_modern/components/bitrix/sale.order.ajax/lider_style/template.php)
 * — магазин работает в одном городе (Елабуга), отдельный выбор города не
 * нужен. Но именно по этому свойству Bitrix подбирает доступные службы
 * доставки: если оно не заполнено (а у части уже сохранённых профилей
 * покупателей оно пустое — заполнить его им было просто негде), список
 * доставок приходит пустым, и весь блок "Способ получения" схлопывается до
 * заглушки "Заполните контакты для расчёта доставки", даже если имя и
 * телефон уже подставлены из профиля.
 *
 * Более ранняя, так и не включённая попытка того же самого — через
 * $_SESSION['location_code'], см. /include/add_addres_in_user.php — была
 * привязана к виджету выбора города, который, судя по всему, на сайте не
 * задействован, поэтому сессионное значение никогда не устанавливалось.
 * Здесь то же самое, но без зависимости от сессии: город один, поэтому
 * просто всегда подставляем код Елабуги, если покупатель явно не выбрал
 * другое значение сам.
 */

AddEventHandler('sale', 'OnSaleComponentOrderProperties', 'fillDefaultOrderLocation');

function fillDefaultOrderLocation(&$arUserResult, $request, &$arParams, &$arResult)
{
    if (!is_array($arUserResult['ORDER_PROP'] ?? null)) {
        return;
    }
    if (!CModule::IncludeModule('sale')) {
        return;
    }

    $locationCode = getDefaultShopLocationCode();
    if ($locationCode === '') {
        return;
    }

    $orderPost = $request->getPost('order');
    $orderPost = is_array($orderPost) ? $orderPost : [];

    $res = CSaleOrderProps::GetList([], ['TYPE' => 'LOCATION', 'ACTIVE' => 'Y']);
    while ($prop = $res->Fetch()) {
        $propId = $prop['ID'];
        if (!array_key_exists($propId, $arUserResult['ORDER_PROP'])) {
            continue;
        }
        if (!empty($arUserResult['ORDER_PROP'][$propId])) {
            continue; // уже что-то есть (из сохранённого профиля) — не трогаем
        }
        // Покупатель мог явно выбрать город в самой форме — тогда это придёт
        // POST'ом, и его тоже не трогаем.
        if ($request->getPost('ORDER_PROP_' . $propId) || !empty($orderPost['ORDER_PROP_' . $propId])) {
            continue;
        }
        $arUserResult['ORDER_PROP'][$propId] = $locationCode;
    }
}

function getDefaultShopLocationCode(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cache = new \Bitrix\Main\Data\Cache();
    $cacheId = 'default_shop_location_code_elabuga_v1';
    $cachePath = '/lider/order_location';

    if ($cache->initCache(86400 * 30, $cacheId, $cachePath)) {
        $cached = (string)$cache->getVars();
        return $cached;
    }

    global $DB;
    $code = '';
    $rsName = $DB->Query("SELECT LOCATION_ID FROM b_sale_loc_name WHERE NAME LIKE '%Елабуга%' LIMIT 1");
    if ($rowName = $rsName->Fetch()) {
        $locationId = (int)$rowName['LOCATION_ID'];
        if ($locationId > 0) {
            $rsCode = $DB->Query("SELECT CODE FROM b_sale_location WHERE ID = " . $locationId . " LIMIT 1");
            if ($rowCode = $rsCode->Fetch()) {
                $code = (string)$rowCode['CODE'];
            }
        }
    }

    if ($cache->startDataCache()) {
        $cache->endDataCache($code);
    }

    $cached = $code;
    return $cached;
}
