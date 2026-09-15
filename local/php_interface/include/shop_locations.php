<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Общие данные о магазинах (адреса, график, телефоны) — единый источник для
// шапки, подвала, страницы «Контакты» и автосервиса, чтобы не дублировать
// и не рассинхронизировать номера при изменениях.
function getShopLocations(): array
{
    return [
        [
            'id'      => 'neftyanikov',
            'short'   => 'Нефтяников, 4',
            'address' => 'РТ, Елабуга, пр-т Нефтяников, 4',
            // Уникальное слово из адреса — чтобы находить телефоны магазина по
            // адресу точки самовывоза из sale.order.ajax, где имя формируется
            // Bitrix-доставкой и может немного отличаться форматированием
            // (без "РТ,", с иной расстановкой запятых).
            'keyword' => 'нефтяников',
            'hours'   => 'Пн-Вс: 8:00–19:00',
            // Открытие/закрытие в структурированном виде — для отсчёта времени
            // до закрытия в шапке (getShopOpenStatusText ниже). Магазин работает
            // ежедневно, поэтому дня недели отдельно не храним.
            'open'    => '08:00',
            'close'   => '19:00',
            'coords'  => [55.74767080512837, 52.00686362268443],
            'phones'  => [
                ['label' => 'Отдел ВАЗ',             'display' => '+7 (85557) 3-20-50 доб.1', 'tel' => '+78555732050,1'],
                ['label' => 'Отдел ВАЗ (моб.)',       'display' => '+7 (993) 423-20-50',       'tel' => '+79934232050'],
                ['label' => 'Отдел Иномарки',         'display' => '+7 (85557) 3-20-50 доб.2', 'tel' => '+78555732050,2'],
                ['label' => 'Отдел Иномарки (моб.)',  'display' => '+7 (987) 262-45-85',       'tel' => '+79872624585'],
            ],
        ],
        [
            'id'      => 'baki-urmanche',
            'short'   => 'Баки Урманче, 17а',
            'address' => 'РТ, Елабуга, ул. Баки Урманче, 17а',
            'keyword' => 'урманче',
            'hours'   => 'Пн-Вс: 8:00–19:00',
            'open'    => '08:00',
            'close'   => '19:00',
            'coords'  => [55.77622330421297, 52.02240121966068],
            'phones'  => [
                ['label' => 'Отдел ВАЗ',      'display' => '+7 (85557) 99-3-99',  'tel' => '+78555799399'],
                ['label' => 'Отдел Иномарки', 'display' => '+7 (919) 626-03-09', 'tel' => '+79196260309'],
            ],
        ],
    ];
}

// Статус "открыто / закрыто" по времени сервера, с отсчётом до закрытия —
// сайт хостится и работает по московскому времени, текущее время
// пользователей на сайте тоже считается московским, поэтому никакого
// перевода часового пояса не требуется, берём время сервера как есть.
function getShopOpenStatus(array $shop): array
{
    if (empty($shop['open']) || empty($shop['close'])) {
        return ['isOpen' => null, 'text' => ''];
    }
    $now = new DateTime();
    $open = DateTime::createFromFormat('H:i', $shop['open']);
    $close = DateTime::createFromFormat('H:i', $shop['close']);
    if (!$open || !$close) {
        return ['isOpen' => null, 'text' => ''];
    }
    $open->setDate((int)$now->format('Y'), (int)$now->format('n'), (int)$now->format('j'));
    $close->setDate((int)$now->format('Y'), (int)$now->format('n'), (int)$now->format('j'));

    if ($now >= $open && $now < $close) {
        $minutesLeft = (int)ceil(($close->getTimestamp() - $now->getTimestamp()) / 60);
        $h = intdiv($minutesLeft, 60);
        $m = $minutesLeft % 60;
        $parts = [];
        if ($h > 0) $parts[] = $h . ' ч';
        if ($m > 0 || !$parts) $parts[] = $m . ' мин';
        return ['isOpen' => true, 'text' => 'закроется через ' . implode(' ', $parts)];
    }

    return ['isOpen' => false, 'text' => 'закрыто, откроется в ' . $shop['open']];
}

// Находит магазин по адресу точки самовывоза (например, из имени доставки
// в sale.order.ajax) — сравнение по ключевому слову, а не по строке целиком,
// т.к. формат адреса у доставки и у магазина может немного отличаться.
function findShopByAddress(string $address): ?array
{
    $needle = mb_strtolower($address);
    foreach (getShopLocations() as $shop) {
        if (mb_strpos($needle, $shop['keyword']) !== false) {
            return $shop;
        }
    }
    return null;
}

// Телефоны для записи на СТО (автосервис) — отдельная пара номеров,
// озвученных для этой цели отдельно от общих отделов магазина.
function getAutoservicePhones(): array
{
    return [
        ['display' => '+7 (85557) 3-20-50 доб.4', 'tel' => '+78555732050,4'],
        ['display' => '+7 (987) 262-45-85',       'tel' => '+79872624585'],
    ];
}
