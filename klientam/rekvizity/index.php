<?php
// Старая страница реквизитов — дубль /rekvizity/ (там полная юридическая
// информация). Постоянный редирект, чтобы поисковики склеили адреса.
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
LocalRedirect("/rekvizity/", false, "301 Moved Permanently");
die();
