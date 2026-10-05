<?php
// Страница «не найдено»: на неё ведёт ErrorDocument в .htaccess и
// \Bitrix\Iblock\Component\Tools::process404() (каталог, магазины). Код ответа
// обязательно 404 — иначе поисковики индексируют её как обычную страницу.
include_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/urlrewrite.php';
CHTTP::SetStatus('404 Not Found');
@define('ERROR_404', 'Y');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Страница не найдена");
$APPLICATION->SetPageProperty("title", "Страница не найдена — ЛИДЕР, автозапчасти в Елабуге");
$APPLICATION->SetPageProperty("robots", "noindex, follow");
?>
<div class="container" style="padding:48px 0 64px;text-align:center;">
    <h1 class="section-title" style="justify-content:center;">Страница не найдена</h1>
    <p style="color:var(--gray);margin:12px 0 24px;">Возможно, ссылка устарела или товар снят с продажи. Найдите нужную деталь через поиск или каталог.</p>
    <form class="search-form" action="/search/" style="max-width:520px;margin:0 auto 24px;">
        <input type="text" name="q" placeholder="Поиск по VIN, названию или артикулу...">
        <button type="submit"><svg class="icon"><use href="#icon-search"></use></svg></button>
    </form>
    <div class="hero__buttons" style="justify-content:center;">
        <a href="/catalog/" class="btn btn--primary btn--lg">Каталог запчастей</a>
        <a href="/podbor-po-vin/" class="btn btn--white btn--lg">Подбор по VIN</a>
        <a href="/contacts/" class="btn btn--white btn--lg">Контакты</a>
    </div>
</div>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
