<?php
define("apiClientId", 18111); // Ваш ID клиента на сайте https://my.neoriginal.ru
define("apiKey", "10d416e8c5f28439a7fdf5d2004e2b06"); //Ваш API ключ
define("apiDomain", "lider.netkama.ru"); //Название Вашего домена, для которого сгенерирован API ключ
define("apiStaticContentHost", "//static.ilcats.ru"); //Название домена со статическим контентом (на данный момент используются только изображения с isStaticImage = 1
define("apiImagesHost", "//images.ilcats.ru"); //Название домена с изображениями
define("apiVersion", "2.0"); //Версия АПИ
define("apiArticlePartLink", "https://www.neoriginal.ru/spares/<%API_URL_BRAND_NAME%>/<%API_URL_PART_NUMBER%>");
//Шаблон ссылки на поисковую форму Вашего сайта
define("apiArticlePartLinkTarget", 1); //Открывать поисковую форму: 0 - в текущем окне; 1 - в новом окне
define("apiPartWBrandLink", 'https://www.neoriginal.ru/spares/<%API_URL_BRAND_NAME%>/<%API_URL_PART_NUMBER%>');
//Шаблон ссылки на поисковую форму Вашего сайта (только для каталога Maintenance и для применяемости)
define("apiPartWBrandLinkTarget", 1); //Открывать поисковую форму: 0 - в текущем окне; 1 - в новом окне
define("apiPartUsageTarget", 1);
//Открывать ссылки с дополнительной информацией о запчасти (изображение, применяемость и аналоги): 0 - в текущем окне; 1 - в новом окне

define("apiPartInfo", $partInfoValue); //Флаг выдачи АПИ дополнительной информации о запчасти в методе getParts
// 1: Выдавать всю доступную информацию (изображение, применяемость и аналоги)
// 2: Применяемость и изображение
// 3: Применяемость и аналоги
// 4: Только применяемость
// 5: Только изображение
// 6: Только аналоги
// 7: Изображение и аналоги
// Другие значения: не выдавать ничего


define("apiClientIpAddress", $_SERVER["HTTP_CF_CONNECTING_IP"]);
//define("apiClientIpAddress", $_SERVER['REMOTE_ADDR']);
// ip адрес клиента Вашего сайта. REMOTE_ADDR, HTTP_X_REAL_IP, HTTP_CF_CONNECTING_IP и т.п...
define("apiClientUserAgent", $_SERVER["HTTP_USER_AGENT"]);
// User-agent клиента

define("apiHttpCatalogsPath", '');    // Относительный путь до главной страницы каталогов
define("apiIlcatsIsPlugin", 0);    // 1 - Если код используется на странице, где есть свои теги <html>, <head> и т.п...
define("apiLoadJquery", 1);        // 1 - Загружать JQuery

$apiActiveLanguages = [
//	'ru',
//	'en',
//	'de',
//	'fr',
];
// Доступные языки интерфейса
// Если массив пустой - все доступные в API
// Иначе - список нужных.

define("apiServerUrl", "https://api.ilcats.ru/"); // Url адрес API-сервера


?>