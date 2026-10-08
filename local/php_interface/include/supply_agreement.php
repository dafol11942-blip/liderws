<?php
// === СОГЛАШЕНИЕ НА ПОСТАВКУ АВТОЗАПЧАСТЕЙ И АКСЕССУАРОВ ===
//
// Покупатель при оформлении заказа обязательно отмечает чекбокс согласия с
// Соглашением (agree_supply, см. lider_style/template.php и проверку в
// order_create_handler.php). Чекаут доступен только после входа по
// одноразовому SMS-коду (include/require_phone_auth.php) — поэтому согласие
// оформляется как подписание простой электронной подписью (63-ФЗ, ст. 9;
// ГК РФ, п. 2 ст. 160): ключ ПЭП — учётная запись, вход в которую подтверждён
// кодом из SMS, условие об этом есть в самом Соглашении (п. 1.4).
//
// Подписанный экземпляр (данные заказа + текст условий + реквизиты подписи:
// кто, когда, с какого IP, хэш текста) собирается в самодостаточный HTML,
// сохраняется в b_file и прикладывается к письму SALE_NEW_ORDER
// (attachSupplyAgreementToOrderMail(), событие main:OnBeforeEventAdd,
// регистрируется в init.php). Хэш документа пишется в историю заказа — по нему
// потом доказывается, что экземпляр не менялся.
//
// Текст условий — ОДИН источник (getSupplyAgreementTermsHtml()) и для
// публичной страницы /klientam/soglashenie-o-postavke/, и для подписанного
// экземпляра. Меняете текст — обязательно поменяйте SUPPLY_AGREEMENT_VERSION:
// в каждом экземпляре фиксируется редакция, действовавшая при оформлении.

if (!defined('SUPPLY_AGREEMENT_VERSION')) define('SUPPLY_AGREEMENT_VERSION', '08.10.2026');
if (!defined('SUPPLY_AGREEMENT_URL')) define('SUPPLY_AGREEMENT_URL', '/klientam/soglashenie-o-postavke/');

if (!function_exists('getSupplyAgreementSeller')) {
    /** Реквизиты Исполнителя — те же, что на /rekvizity/. */
    function getSupplyAgreementSeller(): array
    {
        return [
            'NAME'       => 'Индивидуальный предприниматель Винокуров Сергей Владимирович',
            'SHORT_NAME' => 'ИП Винокуров С. В.',
            'SIGNER'     => 'Винокуров Сергей Владимирович',
            'INN'        => '164604616640',
            'OGRNIP'     => '314167418800021',
            'ADDRESS'    => '423600, Республика Татарстан, г. Елабуга, ул. Швалева, д. 8',
            'SHOP'       => 'магазин «Лидер»: г. Елабуга, пр-т Нефтяников, д. 4; ул. Баки Урманче, д. 17а',
            'PHONE'      => '+7 (85557) 3-20-50',
            'EMAIL'      => 'lider-16@bk.ru',
            'SITE'       => 'liderws.ru',
        ];
    }
}

if (!function_exists('getSupplyAgreementTermsHtml')) {
    /** Текст условий Соглашения (без данных заказа и без подписей). */
    function getSupplyAgreementTermsHtml(): string
    {
        $s = array_map('htmlspecialchars', getSupplyAgreementSeller());
        $version = htmlspecialchars(SUPPLY_AGREEMENT_VERSION);
        $url = 'https://' . $s['SITE'] . SUPPLY_AGREEMENT_URL;

        return <<<HTML
<h2 class="sa-title">Соглашение на поставку автозапчастей и аксессуаров</h2>
<p class="sa-version">Редакция от {$version}. Опубликовано: <a href="{$url}">{$url}</a></p>

<p>Настоящее Соглашение заключается между {$s['NAME']} (ИНН {$s['INN']}, ОГРНИП {$s['OGRNIP']}, адрес: {$s['ADDRESS']}; {$s['SHOP']}), далее — «Исполнитель», и физическим лицом, оформившим заказ на сайте {$s['SITE']}, далее — «Заказчик». Соглашение является неотъемлемой частью договора розничной купли-продажи товаров дистанционным способом (далее — «Договор») и определяет условия заказа, поставки, возврата и гарантии на автозапчасти и аксессуары. Отношения сторон регулируются Гражданским кодексом РФ, Законом РФ от 07.02.1992 № 2300-1 «О защите прав потребителей» (далее — «Закон») и Правилами продажи товаров по договору розничной купли-продажи, утверждёнными постановлением Правительства РФ от 31.12.2020 № 2463 (далее — «Правила продажи»).</p>

<h3>1. Заключение Договора и электронная подпись</h3>
<p>1.1. Исполнитель обязуется передать в собственность Заказчика товары, указанные в заказе (наименование, количество, цена и ориентировочный срок поставки указываются в заказе), а Заказчик — принять и оплатить их.</p>
<p>1.2. Информация о товарах на сайте {$s['SITE']} вместе с настоящим Соглашением является публичной офертой (ст. 437, 494 ГК РФ). Акцептом оферты (ст. 438 ГК РФ) является оформление заказа на сайте с проставлением отметки о согласии с настоящим Соглашением. Договор считается заключённым с момента оформления заказа.</p>
<p>1.3. Оформляя заказ, Заказчик подтверждает, что до заключения Договора ознакомился с информацией о товаре, его цене, Исполнителе, способах оплаты и доставки, порядке и сроках возврата товара (ст. 8–10, 26.1 Закона).</p>
<p>1.4. Стороны договорились (ч. 2 ст. 6 Федерального закона от 06.04.2011 № 63-ФЗ «Об электронной подписи», п. 2 ст. 160 ГК РФ), что действия Заказчика на сайте после входа в личный кабинет с подтверждением одноразовым кодом, направленным в SMS на номер телефона Заказчика, считаются совершёнными Заказчиком лично, а проставление отметки о согласии с Соглашением и нажатие кнопки «Оформить заказ» — подписанием Соглашения простой электронной подписью. Ключом простой электронной подписи является учётная запись Заказчика на сайте в сочетании с подтверждённым по SMS номером телефона. Подписанный таким образом электронный документ признаётся равнозначным документу на бумажном носителе, подписанному собственноручной подписью Заказчика. Заказчик обязуется не передавать третьим лицам одноразовые коды и доступ к своему номеру телефона.</p>
<p>1.5. Со стороны Исполнителя условия Соглашения выражены в публичной оферте и не требуют отдельного подписания. Экземпляр подписанного Соглашения с данными заказа, датой, временем и реквизитами подписи направляется Заказчику на адрес электронной почты, указанный при оформлении заказа, и хранится у Исполнителя.</p>

<h3>2. Подбор и заказ товара</h3>
<p>2.1. Подбор товара выполняется по идентификационному номеру (VIN) и данным регистрационных документов автомобиля, по каталожному номеру (артикулу) либо по иным сведениям, предоставленным Заказчиком. Исполнитель не отвечает за несоответствие товара автомобилю, если оно вызвано недостоверностью предоставленных Заказчиком сведений либо тем, что фактическая комплектация автомобиля не соответствует его регистрационным данным или заводской спецификации. Такой товар, если он надлежащего качества, может быть возвращён в порядке раздела 4.</p>
<p>2.2. Если Заказчик сам выбрал товар по каталожному номеру (артикулу) или сам выбрал аналог, ответственность за применимость товара к конкретному автомобилю несёт Заказчик.</p>
<p>2.3. Аналог — деталь другого изготовителя, взаимозаменяемая с оригинальной по каталогам изготовителей. Аналог предлагается по согласованию с Заказчиком. За качество аналога Исполнитель отвечает в соответствии с Законом, как за любой проданный товар; гарантийный срок на аналог устанавливается его изготовителем.</p>
<p>2.4. Срок поставки заказного товара зависит от наличия на складах поставщиков и условий доставки и указывается в заказе как ориентировочный. Информацию о движении заказа Исполнитель предоставляет по запросу Заказчика, а также в личном кабинете на сайте. Если поставщик отказал в поставке, Исполнитель уведомляет Заказчика и возвращает уплаченную за такой товар сумму не позднее 10 дней с момента уведомления.</p>

<h3>3. Хранение и получение заказа</h3>
<p>3.1. Поступивший товар хранится в пункте выдачи 10 календарных дней с момента уведомления Заказчика о его поступлении (SMS, звонок, электронная почта или личный кабинет).</p>
<p>3.2. Если Заказчик не получил товар в этот срок и не согласовал с Исполнителем иной срок, Исполнитель вправе после повторного уведомления отказаться от исполнения Договора (п. 3 ст. 484, ст. 328 ГК РФ). В этом случае Исполнитель возвращает Заказчику уплаченную сумму не позднее 10 дней; удержание из неё допускается только в случаях и в размере, предусмотренных законом. Предоплата не аннулируется.</p>
<p>3.3. При получении Заказчик проверяет наименование, количество, комплектность и внешний вид товара. Недостатки, которые могли быть обнаружены при осмотре, рекомендуется указать при получении.</p>

<h3>4. Отказ от товара надлежащего качества</h3>
<p>4.1. Заказчик вправе отказаться от товара в любое время до его передачи, а после передачи — в течение 7 дней (п. 4 ст. 26.1 Закона). Если в разделе «Гарантия и возврат» на сайте установлен более длительный срок, применяется он.</p>
<p>4.2. Возврат товара надлежащего качества возможен, если сохранены его товарный вид, потребительские свойства, заводская упаковка, пломбы и ярлыки, а также документ, подтверждающий покупку (его отсутствие не лишает Заказчика возможности ссылаться на другие доказательства покупки). Деталь, которая устанавливалась на автомобиль, утрачивает товарный вид и потребительские свойства нового товара.</p>
<p>4.3. Не подлежит возврату товар надлежащего качества, имеющий индивидуально-определённые свойства, если он может быть использован исключительно приобретающим его Заказчиком (абз. 4 п. 4 ст. 26.1 Закона). Такой товар отмечается на сайте как невозвратный, и при оформлении заказа Заказчик даёт на это отдельное согласие.</p>
<p>4.4. При отказе от товара Исполнитель возвращает уплаченную сумму, за исключением расходов на доставку возвращённого товара от Заказчика, не позднее 10 дней с даты предъявления требования (п. 4 ст. 26.1 Закона), тем же способом, которым производилась оплата, если стороны не договорились об ином.</p>

<h3>5. Качество и гарантия</h3>
<p>5.1. Гарантийный срок на товар устанавливается его изготовителем и исчисляется с момента передачи товара Заказчику. Если изготовитель гарантийный срок не установил, требования, связанные с недостатками товара, могут быть предъявлены в пределах двух лет с момента передачи (п. 1 ст. 19 Закона).</p>
<p>5.2. При обнаружении недостатка Заказчик вправе предъявить требования, предусмотренные ст. 18 Закона, в письменной форме по адресу Исполнителя или по электронной почте {$s['EMAIL']}, приложив описание недостатка. Исполнитель принимает товар и при необходимости проводит проверку качества, а при споре о причинах недостатка — экспертизу за свой счёт (п. 5 ст. 18 Закона); Заказчик вправе присутствовать при проверке и экспертизе.</p>
<p>5.3. Многие недостатки автозапчастей проявляются только после установки, поэтому для быстрого рассмотрения претензии рекомендуется приложить заказ-наряд или акт выполненных работ организации, установившей деталь, и акт дефектовки (диагностики). Отсутствие этих документов не является основанием для отказа в принятии претензии.</p>
<p>5.4. Исполнитель не отвечает за недостатки товара, если докажет, что они возникли после его передачи Заказчику вследствие нарушения правил использования, хранения, транспортировки или установки товара, действий третьих лиц или непреодолимой силы (п. 6 ст. 18 Закона), в частности:</p>
<ul>
<li>повреждения в результате ДТП, небрежной эксплуатации, перегрузки автомобиля, участия в спортивных состязаниях, преодоления водных преград, езды по бездорожью;</li>
<li>неисправности деталей топливной системы и системы выпуска вследствие применения некачественного или несезонного топлива (в том числе загрязнённого или этилированного);</li>
<li>установка с нарушением требований изготовителя автомобиля или детали, в том числе неквалифицированный монтаж, непарная замена деталей подвески там, где изготовитель требует замены попарно (пружины, амортизаторы, стойки стабилизатора и т. п.), замена амортизаторов без защитных комплектов (отбойник и пыльник);</li>
<li>повреждения деталей электрооборудования (датчиков, переключателей, расходомеров и т. п.) вследствие неисправности электропроводки автомобиля, короткого замыкания или неправильного подключения;</li>
<li>конструктивные изменения автомобиля и их последствия для других деталей и узлов;</li>
<li>естественный износ расходных деталей и материалов в пределах их нормального ресурса (щётки стеклоочистителя, приводные ремни, тормозные колодки, диски и барабаны, диски сцепления, свечи зажигания, лампы, предохранители, прокладки, фильтры, эксплуатационные жидкости); производственные недостатки таких товаров рассматриваются на общих основаниях;</li>
<li>посторонние шумы, скрипы или вибрация, не влияющие на характеристики и нормальную работу агрегатов автомобиля, а также следы подтекания жидкостей без заметного снижения их уровня — такие проявления недостатком товара не являются;</li>
<li>внешние повреждения стёкол и приборов освещения, возникшие после передачи товара.</li>
</ul>

<h3>6. Прочие условия</h3>
<p>6.1. Условия Соглашения, ухудшающие положение Заказчика по сравнению с Законом, не применяются; вместо них действуют нормы Закона (п. 1 ст. 16 Закона).</p>
<p>6.2. Претензии направляются по адресу Исполнителя или по электронной почте {$s['EMAIL']}, телефон {$s['PHONE']}. Споры разрешаются путём переговоров, а при недостижении согласия — в суде по правилам подсудности, установленным законом, в том числе по выбору Заказчика в соответствии с п. 2 ст. 17 Закона.</p>
<p>6.3. Персональные данные Заказчика обрабатываются в соответствии с <a href="https://{$s['SITE']}/soglasie/">Политикой обработки персональных данных</a> для исполнения Договора.</p>
<p>6.4. Исполнитель вправе изменять Соглашение, публикуя новую редакцию на сайте. К заказу применяется редакция, действовавшая в момент его оформления; она указывается в экземпляре Соглашения, направляемом Заказчику.</p>
HTML;
    }
}

if (!function_exists('supplyAgreementFormatDate')) {
    /** «8 октября 2026 г.» */
    function supplyAgreementFormatDate(\DateTimeInterface $dt): string
    {
        $months = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        return (int)$dt->format('j') . ' ' . $months[(int)$dt->format('n')] . ' ' . $dt->format('Y') . ' г.';
    }
}

if (!function_exists('buildSupplyAgreementAcceptance')) {
    /**
     * Реквизиты подписания — снимаются в момент оформления заказа (запрос с
     * отмеченным чекбоксом agree_supply от авторизованного по SMS покупателя).
     */
    function buildSupplyAgreementAcceptance(): array
    {
        global $USER;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Moscow'));

        $user = [];
        if (is_object($USER) && $USER->IsAuthorized()) {
            $user = (array)\CUser::GetByID($USER->GetID())->Fetch();
        }

        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        return [
            'SIGNED_AT'       => $now->format('d.m.Y H:i:s') . ' (МСК, UTC+3)',
            'SIGNED_AT_TS'    => $now->getTimestamp(),
            'SIGNED_DATE'     => supplyAgreementFormatDate($now),
            'IP'              => (string)($_SERVER['REMOTE_ADDR'] ?? '') . ($forwarded !== '' ? ' (X-Forwarded-For: ' . $forwarded . ')' : ''),
            'USER_AGENT'      => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            'USER_ID'         => (int)($user['ID'] ?? 0),
            'USER_LOGIN'      => (string)($user['LOGIN'] ?? ''),
            'USER_PHONE'      => trim((string)($user['PERSONAL_PHONE'] ?? '')),
            'USER_FIO'        => trim(implode(' ', array_filter([
                trim((string)($user['LAST_NAME'] ?? '')),
                trim((string)($user['NAME'] ?? '')),
                trim((string)($user['SECOND_NAME'] ?? '')),
            ]))),
            'USER_EMAIL'      => trim((string)($user['EMAIL'] ?? '')),
            'VERSION'         => SUPPLY_AGREEMENT_VERSION,
            'TERMS_HASH'      => hash('sha256', getSupplyAgreementTermsHtml()),
        ];
    }
}

if (!function_exists('buildSignedSupplyAgreementHtml')) {
    /** Подписанный экземпляр: заказ покупателя + условия + реквизиты подписи. */
    function buildSignedSupplyAgreementHtml(\Bitrix\Sale\Order $order, array $acc): string
    {
        $h = function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); };
        $money = function ($v): string { return number_format((float)$v, 2, ',', ' '); };
        $s = getSupplyAgreementSeller();

        $orderNumber = (string)($order->getField('ACCOUNT_NUMBER') ?: $order->getId());

        // Заказчик — из свойств заказа, с фолбэком на профиль (тот, что
        // подтверждён SMS при входе).
        $props = $order->getPropertyCollection();
        $fio = $phone = $email = '';
        try { $fio = trim((string)(($p = $props->getPayerName()) ? $p->getValue() : '')); } catch (\Throwable $e) {}
        try { $phone = trim((string)(($p = $props->getPhone()) ? $p->getValue() : '')); } catch (\Throwable $e) {}
        try { $email = trim((string)(($p = $props->getUserEmail()) ? $p->getValue() : '')); } catch (\Throwable $e) {}
        if ($fio === '') $fio = $acc['USER_FIO'] ?? '';
        if ($phone === '') $phone = $acc['USER_PHONE'] ?? '';
        if ($email === '') $email = $acc['USER_EMAIL'] ?? '';

        $rows = '';
        $n = 0;
        foreach ($order->getBasket() as $item) {
            $article = '';
            foreach ($item->getPropertyCollection() as $p) {
                if ($p->getField('CODE') === 'SUPPLIER_ARTICLE') { $article = (string)$p->getField('VALUE'); break; }
            }
            $n++;
            $rows .= '<tr><td class="c">' . $n . '</td><td>' . $h($item->getField('NAME'))
                . '</td><td>' . $h($article) . '</td><td class="r">' . $h((float)$item->getQuantity())
                . '</td><td class="c">шт</td><td class="r">' . $money($item->getPrice())
                . '</td><td class="r">' . $money($item->getFinalPrice()) . '</td></tr>';
        }
        $deliveryPrice = (float)$order->getDeliveryPrice();
        if ($deliveryPrice > 0) {
            $n++;
            $rows .= '<tr><td class="c">' . $n . '</td><td>Доставка</td><td></td><td class="r">1</td><td class="c">усл</td><td class="r">'
                . $money($deliveryPrice) . '</td><td class="r">' . $money($deliveryPrice) . '</td></tr>';
        }

        $orderDate = $acc['SIGNED_DATE'] ?? supplyAgreementFormatDate(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Moscow')));
        $terms = getSupplyAgreementTermsHtml();

        $signerName = $fio !== '' ? $fio : 'не указано';
        $authNote = !empty($acc['USER_ID'])
            ? 'учётная запись № ' . $h($acc['USER_ID']) . ($acc['USER_LOGIN'] !== '' ? ' (логин ' . $h($acc['USER_LOGIN']) . ')' : '')
              . ', вход подтверждён одноразовым кодом из SMS на номер ' . $h($acc['USER_PHONE'] !== '' ? $acc['USER_PHONE'] : $phone)
            : 'учётная запись не определена';

        return '<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Соглашение на поставку — заказ № ' . $h($orderNumber) . '</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45;color:#111;background:#fff;margin:0;padding:24px}
.sa-doc{max-width:820px;margin:0 auto}
h1{font-size:19px;margin:0 0 14px;padding-bottom:6px;border-bottom:2px solid #111}
h2.sa-title{font-size:16px;text-align:center;margin:28px 0 4px}
.sa-version{text-align:center;font-size:11px;color:#555;margin:0 0 14px}
h3{font-size:14px;margin:16px 0 6px}
p{margin:0 0 6px;text-align:justify}
ul{margin:0 0 6px;padding-left:22px}
table.items{width:100%;border-collapse:collapse;margin:10px 0}
table.items th,table.items td{border:1px solid #444;padding:4px 6px;vertical-align:top}
table.items th{background:#f0f0f0}
.r{text-align:right;white-space:nowrap}.c{text-align:center}
.total{text-align:right;font-weight:bold;font-size:14px}
.parties td{vertical-align:top;padding:2px 10px 2px 0}
.sign{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:22px}
.sign__box{border:1.5px solid #1a4fa0;border-radius:6px;padding:10px 12px;font-size:12px}
.sign__box b{display:block;font-size:13px;margin-bottom:4px}
.sign__stamp{color:#1a4fa0;font-weight:bold;text-transform:uppercase;font-size:11px;margin-bottom:6px}
.meta{font-size:10px;color:#555;margin-top:16px;word-break:break-all}
@media (max-width:640px){body{padding:12px}.sign{grid-template-columns:1fr}}
@media print{body{padding:0}}
</style></head><body><div class="sa-doc">
<h1>Заказ покупателя № ' . $h($orderNumber) . ' от ' . $h($orderDate) . '</h1>
<table class="parties">
<tr><td>Исполнитель:</td><td><b>' . $h($s['NAME']) . ', ИНН ' . $h($s['INN']) . ', ОГРНИП ' . $h($s['OGRNIP']) . ', ' . $h($s['ADDRESS']) . ', тел.: ' . $h($s['PHONE']) . '</b></td></tr>
<tr><td>Заказчик:</td><td><b>' . $h($signerName) . ($phone !== '' ? ', тел.: ' . $h($phone) : '') . ($email !== '' ? ', e-mail: ' . $h($email) : '') . '</b></td></tr>
</table>
<table class="items">
<thead><tr><th>№</th><th>Товары (работы, услуги)</th><th>Артикул</th><th>Кол-во</th><th>Ед.</th><th>Цена, руб.</th><th>Сумма, руб.</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>
<p class="total">Итого: ' . $money($order->getPrice()) . ' руб.</p>
<p>Всего наименований ' . $n . ', на сумму ' . $money($order->getPrice()) . ' руб.</p>

' . $terms . '

<div class="sign">
<div class="sign__box">
<b>Исполнитель</b>
' . $h($s['NAME']) . '<br>ИНН ' . $h($s['INN']) . ', ОГРНИП ' . $h($s['OGRNIP']) . '<br>
Условия выражены в публичной оферте, опубликованной на сайте ' . $h($s['SITE']) . ' (п. 1.5 Соглашения).
</div>
<div class="sign__box">
<b>Заказчик</b>
<div class="sign__stamp">Подписано простой электронной подписью</div>
' . $h($signerName) . '<br>
Дата и время подписания: ' . $h($acc['SIGNED_AT'] ?? '') . '<br>
Способ: отметка «Я ознакомлен(а) и согласен(на) с условиями Соглашения» и нажатие кнопки «Оформить заказ» на сайте ' . $h($s['SITE']) . '<br>
Ключ ПЭП: ' . $authNote . '<br>
IP-адрес: ' . $h($acc['IP'] ?? '') . '
</div>
</div>
<div class="meta">
Документ сформирован автоматически информационной системой Исполнителя при оформлении заказа № ' . $h($orderNumber) . ' (ID ' . (int)$order->getId() . ').
Редакция Соглашения: ' . $h($acc['VERSION'] ?? SUPPLY_AGREEMENT_VERSION) . '. SHA-256 текста условий: ' . $h($acc['TERMS_HASH'] ?? '') . '.
Браузер: ' . $h($acc['USER_AGENT'] ?? '') . '.
Контрольная сумма (SHA-256) этого файла хранится в истории заказа у Исполнителя.
</div>
</div></body></html>';
    }
}

if (!function_exists('storeSignedSupplyAgreement')) {
    /** @return array{FILE_ID:int, HASH:string} — сохраняет экземпляр в b_file. */
    function storeSignedSupplyAgreement(\Bitrix\Sale\Order $order, array $acceptance): array
    {
        $html = buildSignedSupplyAgreementHtml($order, $acceptance);
        $orderNumber = preg_replace('/[^\w-]+/u', '_', (string)($order->getField('ACCOUNT_NUMBER') ?: $order->getId()));
        $fileId = (int)\CFile::SaveFile([
            'name'      => 'soglashenie-o-postavke-zakaz-' . $orderNumber . '.html',
            'type'      => 'text/html',
            'content'   => $html,
            'MODULE_ID' => 'sale',
            'description' => 'Соглашение на поставку, заказ № ' . $orderNumber,
        ], 'supply_agreements');

        return ['FILE_ID' => $fileId, 'HASH' => hash('sha256', $html)];
    }
}

if (!function_exists('ensureSupplyAgreementStored')) {
    /**
     * Сохраняет подписанный экземпляр один раз за запрос. Данные кладёт
     * order_create_handler.php перед $order->save() в
     * $GLOBALS['SUPPLY_AGREEMENT_PENDING'] = ['ORDER' => Order, 'ACCEPTANCE' => [...]].
     */
    function ensureSupplyAgreementStored(): ?array
    {
        $pending = $GLOBALS['SUPPLY_AGREEMENT_PENDING'] ?? null;
        if (empty($pending['ORDER']) || !$pending['ORDER']->getId()) return null;
        if (!isset($pending['RESULT'])) {
            try {
                $result = storeSignedSupplyAgreement($pending['ORDER'], (array)($pending['ACCEPTANCE'] ?? []));
            } catch (\Throwable $e) {
                $result = ['FILE_ID' => 0, 'HASH' => '', 'ERROR' => $e->getMessage()];
            }
            $GLOBALS['SUPPLY_AGREEMENT_PENDING']['RESULT'] = $result;
            return $result;
        }
        return $pending['RESULT'];
    }
}

if (!function_exists('attachSupplyAgreementToOrderMail')) {
    /**
     * main:OnBeforeEventAdd — к письму о новом заказе (SALE_NEW_ORDER, его шлёт
     * модуль sale прямо внутри $order->save()) прикладываем подписанный
     * экземпляр Соглашения. Ничего не делает для других писем и для заказов,
     * оформленных не через форму сайта.
     */
    function attachSupplyAgreementToOrderMail(&$event, &$lid, &$arFields, &$messageId = null, &$files = null)
    {
        try {
            if ($event !== 'SALE_NEW_ORDER') return;
            $pending = $GLOBALS['SUPPLY_AGREEMENT_PENDING'] ?? null;
            if (empty($pending['ORDER'])) return;
            $order = $pending['ORDER'];
            $orderId = (int)$order->getId();
            if (!$orderId) return;

            $sameOrder = (int)($arFields['ORDER_REAL_ID'] ?? 0) === $orderId
                || (string)($arFields['ORDER_ID'] ?? '') === (string)($order->getField('ACCOUNT_NUMBER') ?: $orderId);
            if (!$sameOrder) return;

            $stored = ensureSupplyAgreementStored();
            if (empty($stored['FILE_ID'])) return;

            if (!is_array($files)) $files = [];
            $files[] = $stored['FILE_ID'];
            $GLOBALS['SUPPLY_AGREEMENT_PENDING']['MAILED'] = true;
        } catch (\Throwable $e) {
            if (function_exists('logSupplierOrderDispatch')) {
                logSupplierOrderDispatch('attachSupplyAgreementToOrderMail: ' . $e->getMessage());
            }
        }
    }
}
