<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Engine\Contract\Controllerable;

class AutoToCatalog extends \CBitrixComponent implements Controllerable
{
    public function configureActions()
    {
        return [
            'getBrands'        => ['prefilters' => []],
            'getModels'        => ['prefilters' => []],
            'getModifications' => ['prefilters' => []],
            'getParts'         => ['prefilters' => []],
            'getModificationInfo' => ['prefilters' => []],
        ];
    }

    public function executeComponent()
    {
        Loader::includeModule('highloadblock');
        $this->arResult['PAGE_MODE'] = $this->arParams['PAGE_MODE'] ?? 'embed';
        $this->arResult['DETAIL_PAGE'] = $this->arParams['DETAIL_PAGE'] ?? '/service-parts/';
        $this->arResult['INITIAL_MODIFICATION'] = (int)($_GET['modification'] ?? 0);
        $this->includeComponentTemplate();
    }

    // ----- AJAX: все марки -----
    public function getBrandsAction(): array
    {
        Loader::includeModule('highloadblock');
        return ['brands' => $this->hlRows('AutoBrands', [], ['UF_NAME' => 'ASC'])];
    }

    // ----- AJAX: модели марки -----
    public function getModelsAction(int $brandId): array
    {
        Loader::includeModule('highloadblock');
        return ['models' => $this->hlRows('AutoModels', ['UF_BRAND_ID' => $brandId], ['UF_NAME' => 'ASC'])];
    }

    // ----- AJAX: модификации модели -----
    public function getModificationsAction(int $modelId): array
    {
        Loader::includeModule('highloadblock');
        return ['modifications' => $this->hlRows('AutoModifications', ['UF_MODEL_ID' => $modelId], ['UF_FULL_NAME' => 'ASC'])];
    }

    // ----- AJAX: запчасти + масла + спецификации -----
    public function getPartsAction(int $modificationId): array
    {
        Loader::includeModule('highloadblock');
        return $this->buildPartsData($modificationId);
    }

    // ----- AJAX: получить brandId и modelId по modificationId (для предзагрузки) -----
    public function getModificationInfoAction(int $modificationId): array
    {
        Loader::includeModule('highloadblock');

        $mod = $this->hlRows('AutoModifications', ['UF_MODIFICATION_ID' => $modificationId], [], 1);
        if (empty($mod)) return ['error' => 'Модификация не найдена'];

        $mod = $mod[0];
        $model = $this->hlRows('AutoModels', ['UF_MODEL_ID' => $mod['UF_MODEL_ID']], [], 1);
        if (empty($model)) return ['error' => 'Модель не найдена'];

        $model = $model[0];
        $brand = $this->hlRows('AutoBrands', ['UF_BRAND_ID' => $model['UF_BRAND_ID']], [], 1);

        return [
            'brandId'  => $model['UF_BRAND_ID'],
            'brandName'=> $brand[0]['UF_NAME'] ?? '',
            'modelId'  => $mod['UF_MODEL_ID'],
            'modelName'=> $model['UF_NAME'],
            'modId'    => $modificationId,
            'modName'  => $mod['UF_FULL_NAME'],
        ];
    }

    private function buildPartsData(int $modificationId): array
    {
        $parts = $this->hlRows('AutoParts', ['UF_MODIFICATION_ID' => $modificationId], ['UF_CATEGORY_ID' => 'ASC']);
        $oils  = $this->hlRows('AutoOils',  ['UF_MODIFICATION_ID' => $modificationId], ['UF_ORDER_POSITION' => 'ASC']);
        $specs = $this->hlRows('AutoSpecifications', ['UF_MODIFICATION_ID' => $modificationId], ['UF_NAME' => 'ASC']);

        $catNames = [
            1 => 'Фильтр масляный', 2 => 'Фильтр воздушный', 3 => 'Фильтр топливный',
            4 => 'Фильтр салона', 5 => 'Колодки тормозные передние', 6 => 'Колодки тормозные задние',
            7 => 'Свечи зажигания', 8 => 'Щётки стеклоочистителя',
        ];

        $grouped = [];
        foreach ($parts as $p) {
            $c = (int)$p['UF_CATEGORY_ID'];
            $grouped[$catNames[$c] ?? "Категория $c"][] = $p;
        }

        $oilsGrouped = [];
        foreach ($oils as $o) {
            $oilsGrouped[$o['UF_TYPE_NAME'] ?: 'Прочее'][] = $o;
        }

        return ['parts' => $grouped, 'oils' => $oilsGrouped, 'specs' => $specs];
    }

    private function hlRows(string $name, array $filter = [], array $order = [], int $limit = 0): array
    {
        static $cache = [];
        if (!isset($cache[$name])) {
            $hl = HighloadBlockTable::getRow(['filter' => ['=NAME' => $name]]);
            $cache[$name] = $hl ? HighloadBlockTable::compileEntity($hl)->getDataClass() : null;
        }
        if (!$cache[$name]) return [];

        $q = $cache[$name]::query()->setSelect(['*']);
        if ($filter) $q->setFilter($filter);
        if ($order)  $q->setOrder($order);
        if ($limit)  $q->setLimit($limit);

        $rows = [];
        foreach ($q->exec() as $r) $rows[] = $r;
        return $rows;
    }
}
