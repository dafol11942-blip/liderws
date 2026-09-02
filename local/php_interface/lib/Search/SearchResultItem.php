<?php
namespace Lider\Search;

class SearchResultItem
{
    public string $source;
    public string $article;
    public string $brand;
    public string $name;
    public float $price;
    public string $currency = 'RUB';
    public int $quantity = 0;
    public int $multiplicity = 1;       // кратность (минимальная партия), 1 = поштучно
    public string $unit = 'шт.';        // единица измерения
    public ?int $deliveryDays = null;
    public ?int $deliveryPeriod = null;
    public ?string $deliveryLabel = null;    // человекочитаемая метка: "Сегодня 13:15 - 16:00"/"Завтра 07:45 - 15:00"/"05.09 07:45 - 15:00"
    public bool $deliveryToday = false;      // деталь можно получить сегодня — для акцентного бейджа на фронте
    public ?string $deliveryDeadline = null; // "ЧЧ:ММ" — до какого времени нужно заказать, чтобы успеть к deliveryLabel
    public ?string $warehouse = null;
    public ?string $stockId = null;
    public ?string $supplierName = null;
    public bool $isSched = false;
    public bool $returnable = true;
    public ?int $localProductId = null;
    public ?string $imageUrl = null;
    public ?string $detailUrl = null;
    public array $raw = [];

    public function toArray(): array
    {
        return [
            'source'          => $this->source,
            'article'         => $this->article,
            'brand'           => $this->brand,
            'name'            => $this->name,
            'price'           => $this->price,
            'currency'        => $this->currency,
            'quantity'        => $this->quantity,
            'multiplicity'    => $this->multiplicity,
            'unit'            => $this->unit,
            'delivery_days'     => $this->deliveryDays,
            'delivery_period'   => $this->deliveryPeriod,
            'delivery_label'    => $this->deliveryLabel,
            'delivery_today'    => $this->deliveryToday,
            'delivery_deadline' => $this->deliveryDeadline,
            'warehouse'       => $this->warehouse,
            'stock_id'        => $this->stockId,
            'supplier_name'   => $this->supplierName,
            'is_sched'        => $this->isSched,
            'returnable'      => $this->returnable,
            'local_id'        => $this->localProductId,
            'image_url'       => $this->imageUrl,
            'detail_url'      => $this->detailUrl,
            'raw'             => $this->raw,
        ];
    }

    public function getDedupeKey(): string
    {
        return md5($this->source . '|' . mb_strtolower($this->article) . '|' . mb_strtolower($this->brand));
    }
}
