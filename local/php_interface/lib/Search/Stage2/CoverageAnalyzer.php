<?php
namespace Lider\Search\Stage2;

use Lider\Supplier\SupplierFactory;

class CoverageAnalyzer
{
    private SupplierFactory $factory;
    private array $allSupplierCodes;

    public function __construct(SupplierFactory $factory)
    {
        $this->factory = $factory;
        $this->allSupplierCodes = array_map(
            fn($s) => $s->getCode(),
            $this->factory->allAvailable()
        );
    }

    public function analyze(array $groupedItems, string $exactKey): array
    {
        $report = [];

        foreach ($groupedItems as $key => $group) {
            if ($key === $exactKey) continue;
            if (empty($group['warehouses']) && empty($group['_by_sup'])) continue;

            // Какие поставщики уже есть
            $present = [];
            // Из _by_sup
            foreach ($group['_by_sup'] ?? [] as $src => $list) {
                if (!empty($list)) $present[] = $src;
            }
            // Из warehouses
            foreach ($group['warehouses'] ?? [] as $wh) {
                $src = $wh['source'] ?? '';
                if ($src !== '' && !in_array($src, $present, true)) {
                    $present[] = $src;
                }
            }

            $missing = array_diff($this->allSupplierCodes, $present);
            if (empty($missing)) continue;

            $report[$key] = [
                'brand'        => $group['brand'] ?? '',
                'article'      => $group['article'] ?? '',
                'present'      => array_values($present),
                'missing'      => array_values($missing),
                'presentCount' => count($present),
            ];

            $this->log("ANALYZE [{$key}] {$group['brand']}|{$group['article']} present=" . implode(',', $present) . " missing=" . implode(',', $missing));
        }

        uasort($report, fn($a, $b) => $a['presentCount'] <=> $b['presentCount']);

        $this->log("ANALYZE total: " . count($report) . " groups need fill, allSuppliers=" . implode(',', $this->allSupplierCodes));
        return $report;
    }

    private function log(string $message): void
    {
        @file_put_contents(
            ($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru') . '/upload/logs/coverage_filler.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}
