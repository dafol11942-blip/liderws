<?php

namespace Lider\Seo;

/**
 * Общий SEO-слой сайта: canonical, robots, Open Graph и структурированные
 * данные schema.org (JSON-LD) для Google, Яндекса и генеративных моделей.
 *
 * Шаблон (header.php) вызывает applyDefaults() до ShowHead() и регистрирует
 * renderHead() через AddBufferContent — то есть теги собираются в самом конце
 * хита, когда страница уже задала title/description и добавила свои сущности
 * (товар, хлебные крошки, услугу, FAQ). Страницам достаточно вызвать нужный
 * метод add…() или set…() в любом месте до footer.php.
 */
final class Seo
{
    public const SITE_URL = 'https://liderws.ru';
    public const BRAND = 'ЛИДЕР';
    public const EMAIL = 'lider-16@bk.ru';
    public const MAIN_PHONE = '+7 (85557) 3-20-50';
    public const DEFAULT_DESCRIPTION = 'Магазин автозапчастей ЛИДЕР в Елабуге: запчасти для ВАЗ (LADA) и иномарок, масла, фильтры, аккумуляторы, шины и диски. Два магазина, подбор по VIN, автосервис и шиномонтаж.';

    // Служебные разделы — в индекс не нужны: корзина, оформление, ЛК,
    // авторизация, результаты поиска, тестовые копии разделов.
    private const NOINDEX_PREFIXES = [
        '/cart/', '/order/', '/personal/', '/auth/', '/search/', '/parts-search/',
        '/ajax/', '/form/', '/catalog-test/', '/test2/', '/max-feedback/',
    ];

    // Параметры, которые порождают дубли листингов (фильтр, сортировка) —
    // такие страницы закрываем noindex, follow: ссылки с них учитываются,
    // а сами они в выдачу не попадают.
    private const NOINDEX_QUERY_PARAMS = ['set_filter', 'del_filter', 'sort', 'arrFilter_P1_MIN', 'arrFilter_P1_MAX'];

    // Фото магазинов для карточек организаций — по id из getShopLocations().
    private const SHOP_IMAGES = [
        'neftyanikov'   => '/local/templates/lider_modern/assets/images/store-neftyanikov.webp',
        'baki-urmanche' => '/local/templates/lider_modern/assets/images/store-urmanche.webp',
    ];

    private const LOGO = '/local/templates/lider_modern/assets/images/logo.png';
    private const DEFAULT_OG_IMAGE = '/local/templates/lider_modern/assets/images/store-neftyanikov.webp';

    private static array $nodes = [];
    private static ?array $breadcrumbs = null;
    private static ?string $canonical = null;
    private static ?string $ogImage = null;
    private static string $ogType = 'website';
    private static bool $withShops = false;

    // ------------------------------------------------------------------
    // API для страниц
    // ------------------------------------------------------------------

    /**
     * Явный canonical (путь или абсолютный URL) — переопределяет вычисленный по текущему URL.
     * $keepPagination — сохранить номер страницы листинга (PAGEN_N), как в автоматическом canonical.
     */
    public static function setCanonical(string $url, bool $keepPagination = false): void
    {
        $query = $keepPagination ? self::paginationQuery() : '';
        self::$canonical = self::absUrl($url) . ($query !== '' ? '?' . $query : '');
    }

    public static function setOgImage(string $url): void
    {
        if ($url !== '') {
            self::$ogImage = self::absUrl($url);
        }
    }

    public static function setOgType(string $type): void
    {
        self::$ogType = $type;
    }

    public static function noindex(): void
    {
        global $APPLICATION;
        $APPLICATION->SetPageProperty('robots', 'noindex, follow');
    }

    /** Произвольный узел schema.org — попадёт в общий @graph страницы. */
    public static function addNode(array $node): void
    {
        self::$nodes[] = $node;
    }

    /** Карточки магазинов (AutoPartsStore с адресом, координатами и графиком). */
    public static function addShops(): void
    {
        self::$withShops = true;
    }

    /**
     * Хлебные крошки в формате каталога: [['NAME' => ..., 'LINK' => ...], ...],
     * у последнего пункта LINK может быть пустым — тогда берётся canonical.
     */
    public static function setBreadcrumbs(array $items): void
    {
        self::$breadcrumbs = $items;
    }

    /** Рендерит видимые хлебные крошки и одновременно регистрирует BreadcrumbList. */
    public static function breadcrumbs(array $items): string
    {
        self::setBreadcrumbs($items);
        $html = '<div class="breadcrumbs container"><ul>';
        $last = count($items) - 1;
        foreach ($items as $i => $item) {
            $name = htmlspecialchars(self::text($item['NAME']));
            $html .= ($i < $last && !empty($item['LINK']))
                ? '<li><a href="' . htmlspecialchars($item['LINK']) . '">' . $name . '</a></li>'
                : '<li>' . $name . '</li>';
        }
        return $html . '</ul></div>';
    }

    /**
     * Блок «Вопросы и ответы»: видимый текст и FAQPage из одного источника,
     * чтобы разметка никогда не расходилась с тем, что видит посетитель.
     * $items — [[вопрос, ответ], ...], ответ может содержать простой HTML.
     */
    public static function faq(array $items, string $title = 'Частые вопросы'): string
    {
        $entities = [];
        $html = '<section class="faq"><h2 class="section-title">' . htmlspecialchars($title) . '</h2>';
        foreach ($items as [$question, $answer]) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($answer, '<a><b><strong><br><ul><li>'))],
            ];
            $html .= '<details class="faq__item"><summary class="faq__q">' . htmlspecialchars($question) . '</summary>'
                . '<div class="faq__a">' . $answer . '</div></details>';
        }
        self::addNode(['@type' => 'FAQPage', 'mainEntity' => $entities]);
        return $html . '</section>';
    }

    /**
     * Страница услуги автосервиса: хлебные крошки + Service, оказываемая
     * автосервисом на пр-те Нефтяников, 4. Описание берётся из description страницы.
     */
    public static function autoservicePage(string $name, string $path): void
    {
        self::setBreadcrumbs([
            ['NAME' => 'Главная', 'LINK' => '/'],
            ['NAME' => 'Автосервис', 'LINK' => '/autoservice/'],
            ['NAME' => $name, 'LINK' => ''],
        ]);
        self::addNode([
            '@type' => 'Service',
            'name' => $name,
            'serviceType' => $name,
            'url' => self::absUrl($path),
            'provider' => ['@id' => self::SITE_URL . '/#organization'],
            'areaServed' => ['@type' => 'City', 'name' => 'Елабуга'],
            'availableChannel' => [
                '@type' => 'ServiceChannel',
                'servicePhone' => ['@type' => 'ContactPoint', 'telephone' => '+7 (85557) 3-20-50 доб. 4', 'contactType' => 'запись в автосервис'],
                'serviceLocation' => ['@id' => self::SITE_URL . '/shop/neftyanikov/#store'],
            ],
        ]);
        self::addShops();
    }

    /** Готовый тег JSON-LD — для кэшируемых шаблонов компонентов, где буфер не перезапускается. */
    public static function jsonLdTag(array $data): string
    {
        if (!isset($data['@context'])) {
            $data = ['@context' => 'https://schema.org'] + $data;
        }
        return '<script type="application/ld+json">' . self::json($data) . '</script>';
    }

    public static function absUrl(string $url): string
    {
        if ($url === '' || preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return self::SITE_URL . '/' . ltrim($url, '/');
    }

    /** Значения из GetNext() приходят HTML-экранированными — в JSON и мета-теги нужен чистый текст. */
    public static function text($value): string
    {
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** Обрезка текста для meta description по границе слова. */
    public static function truncate(string $text, int $limit = 160): string
    {
        $text = self::text($text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $space ?: $limit - 1), ' ,.;:—-') . '…';
    }

    // ------------------------------------------------------------------
    // Вызовы из шаблона
    // ------------------------------------------------------------------

    /** До ShowHead(): robots для служебных разделов и параметров-дублей. */
    public static function applyDefaults(): void
    {
        global $APPLICATION;
        $path = $APPLICATION->GetCurPage(false);
        foreach (self::NOINDEX_PREFIXES as $prefix) {
            if (strpos($path, $prefix) === 0) {
                self::noindex();
                return;
            }
        }
        foreach (array_keys($_GET) as $param) {
            if (in_array($param, self::NOINDEX_QUERY_PARAMS, true) || strpos($param, 'arrFilter_') === 0) {
                self::noindex();
                return;
            }
        }
    }

    /** Отложенный вывод (AddBufferContent): выполняется после того, как страница отработала целиком. */
    public static function renderHead(): string
    {
        global $APPLICATION;
        if (defined('ERROR_404') && ERROR_404 === 'Y') {
            return '';
        }

        $canonical = self::$canonical ?? self::currentCanonical();
        $title = self::text($APPLICATION->GetPageProperty('title') ?: $APPLICATION->GetTitle());
        $description = self::text($APPLICATION->GetPageProperty('description'));
        if ($description === '') {
            $description = self::DEFAULT_DESCRIPTION;
        }
        $image = self::$ogImage ?? self::absUrl(self::DEFAULT_OG_IMAGE);

        $e = static function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
        $out = [];
        $out[] = '<link rel="canonical" href="' . $e($canonical) . '">';
        $out[] = '<meta property="og:type" content="' . $e(self::$ogType) . '">';
        $out[] = '<meta property="og:site_name" content="' . self::BRAND . ' — автозапчасти в Елабуге">';
        $out[] = '<meta property="og:locale" content="ru_RU">';
        $out[] = '<meta property="og:title" content="' . $e($title) . '">';
        $out[] = '<meta property="og:description" content="' . $e($description) . '">';
        $out[] = '<meta property="og:url" content="' . $e($canonical) . '">';
        $out[] = '<meta property="og:image" content="' . $e($image) . '">';
        $out[] = '<meta name="twitter:card" content="summary_large_image">';
        $out[] = '<script type="application/ld+json">' . self::json(self::buildGraph($canonical)) . '</script>';

        return implode("\n    ", $out) . "\n";
    }

    // ------------------------------------------------------------------

    /**
     * Canonical по текущему адресу: всегда https://liderws.ru, без служебных
     * параметров (utm, сортировка, фильтр), но с номером страницы пагинации —
     * вторая и далее страницы листинга самостоятельные, их не склеиваем с первой.
     */
    private static function currentCanonical(): string
    {
        global $APPLICATION;
        $url = self::absUrl($APPLICATION->GetCurPage(false));
        $query = self::paginationQuery();
        return $query !== '' ? $url . '?' . $query : $url;
    }

    private static function paginationQuery(): string
    {
        $keep = [];
        foreach ($_GET as $key => $value) {
            if (preg_match('/^PAGEN_\d+$/', $key) && is_scalar($value) && (int)$value > 1) {
                $keep[$key] = (int)$value;
            }
        }
        return http_build_query($keep);
    }

    private static function buildGraph(string $canonical): array
    {
        $graph = [self::organization(), self::website()];

        if (self::$withShops) {
            foreach (self::shops() as $shop) {
                $graph[] = $shop;
            }
        }

        if (self::$breadcrumbs) {
            $list = [];
            $position = 1;
            foreach (self::$breadcrumbs as $item) {
                $link = !empty($item['LINK']) ? self::absUrl($item['LINK']) : $canonical;
                $list[] = ['@type' => 'ListItem', 'position' => $position++, 'name' => self::text($item['NAME']), 'item' => $link];
            }
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
        }

        foreach (self::$nodes as $node) {
            $graph[] = $node;
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => self::SITE_URL . '/#organization',
            'name' => self::BRAND,
            'alternateName' => ['Автотехцентр ЛИДЕР', 'Магазин автозапчастей «Лидер»', 'liderws.ru'],
            'legalName' => 'ИП Винокуров Сергей Владимирович',
            'taxID' => '164604616640',
            'url' => self::SITE_URL . '/',
            'logo' => ['@type' => 'ImageObject', 'url' => self::absUrl(self::LOGO)],
            'email' => self::EMAIL,
            'telephone' => self::MAIN_PHONE,
            'foundingDate' => '2014-07-19',
            'description' => self::DEFAULT_DESCRIPTION,
            'areaServed' => ['@type' => 'City', 'name' => 'Елабуга'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'пр-т Нефтяников, 4',
                'addressLocality' => 'Елабуга',
                'addressRegion' => 'Республика Татарстан',
                'postalCode' => '423600',
                'addressCountry' => 'RU',
            ],
            'knowsAbout' => ['автозапчасти для ВАЗ (LADA)', 'запчасти для иномарок', 'моторные масла', 'подбор запчастей по VIN', 'техническое обслуживание автомобилей', 'шиномонтаж'],
            'subOrganization' => array_map(static function ($shop) {
                return ['@id' => self::SITE_URL . '/shop/' . $shop['id'] . '/#store'];
            }, self::shopLocations()),
        ];
    }

    private static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::SITE_URL . '/#website',
            'url' => self::SITE_URL . '/',
            'name' => self::BRAND . ' — автозапчасти в Елабуге',
            'inLanguage' => 'ru-RU',
            'publisher' => ['@id' => self::SITE_URL . '/#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => self::SITE_URL . '/search/?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private static function shops(): array
    {
        $result = [];
        foreach (self::shopLocations() as $shop) {
            $phones = array_map(static function ($p) {
                return ['@type' => 'ContactPoint', 'telephone' => $p['display'], 'contactType' => $p['label'], 'areaServed' => 'RU', 'availableLanguage' => 'Russian'];
            }, $shop['phones']);

            $node = [
                '@type' => 'AutoPartsStore',
                '@id' => self::SITE_URL . '/shop/' . $shop['id'] . '/#store',
                'name' => self::BRAND . ' — магазин автозапчастей, ' . $shop['short'],
                'url' => self::SITE_URL . '/shop/' . $shop['id'] . '/',
                'telephone' => $shop['phones'][0]['display'] ?? self::MAIN_PHONE,
                'email' => self::EMAIL,
                'priceRange' => '₽₽',
                'currenciesAccepted' => 'RUB',
                'paymentAccepted' => 'Наличные, банковская карта, безналичный расчёт',
                'parentOrganization' => ['@id' => self::SITE_URL . '/#organization'],
                'address' => [
                    '@type' => 'PostalAddress',
                    // В getShopLocations() адрес вида "РТ, Елабуга, ул. ..." — регион и город выносим в отдельные поля.
                    'streetAddress' => trim(preg_replace('/^РТ,\s*Елабуга,\s*/u', '', $shop['address'])),
                    'addressLocality' => 'Елабуга',
                    'addressRegion' => 'Республика Татарстан',
                    'addressCountry' => 'RU',
                ],
                'contactPoint' => $phones,
            ];
            if (!empty($shop['coords'])) {
                $node['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $shop['coords'][0], 'longitude' => $shop['coords'][1]];
                $node['hasMap'] = 'https://yandex.ru/maps/?pt=' . $shop['coords'][1] . ',' . $shop['coords'][0] . '&z=17&l=map';
            }
            if (!empty($shop['open']) && !empty($shop['close'])) {
                $node['openingHoursSpecification'] = [[
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    'opens' => $shop['open'],
                    'closes' => $shop['close'],
                ]];
            }
            if (isset(self::SHOP_IMAGES[$shop['id']])) {
                $node['image'] = self::absUrl(self::SHOP_IMAGES[$shop['id']]);
            }
            if (!empty($shop['about'])) {
                $node['description'] = $shop['about'];
            }
            $result[] = $node;
        }
        return $result;
    }

    private static function shopLocations(): array
    {
        if (!function_exists('getShopLocations')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/shop_locations.php';
        }
        return getShopLocations();
    }

    private static function json(array $data): string
    {
        // JSON_HEX_TAG — чтобы "</script>" в названии товара не разорвал тег.
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
}
