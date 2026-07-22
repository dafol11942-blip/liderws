<?php
namespace Lider\Search;

class InstantSearcher
{
    private \mysqli $db;

    public function __construct()
    {
        $this->db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $this->db->set_charset('utf8mb4');
    }

    /**
     * Мгновенный поиск по локальной БД.
     * Возвращает те же SearchResultItem[], что и live-поиск.
     */
    public function search(string $article, string $brand = ''): array
    {
        $article = trim($article);
        if (mb_strlen($article) < 2) return [];

        if ($brand !== '') {
            $stmt = $this->db->prepare(
                "SELECT * FROM b_supplier_stock 
                 WHERE article = ? AND brand_normalized = ? AND is_active = 1
                 ORDER BY is_sched ASC, price ASC"
            );
            $brandNorm = BrandNormalizer::normalize($brand);
            $stmt->bind_param('ss', $article, $brandNorm);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM b_supplier_stock 
                 WHERE article = ? AND is_active = 1
                 ORDER BY is_sched ASC, price ASC"
            );
            $stmt->bind_param('s', $article);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $item = new SearchResultItem();
            $item->source       = $row['supplier_code'];
            $item->article      = $row['article'];
            $item->brand        = $row['brand'];
            $item->name         = $row['name'];
            $item->price        = (float)$row['price'];
            $item->quantity     = (int)$row['quantity'];
            $item->warehouse    = $row['warehouse_name'];
            $item->stockId      = $row['stock_id'];
            $item->supplierName = $row['supplier_code'];
            $item->isSched      = (bool)$row['is_sched'];
            $item->deliveryDays = (int)$row['delivery_days'];
            $item->multiplicity = (int)$row['multiplicity'];
            $item->raw['cached'] = true;
            $item->raw['cache_age'] = time() - strtotime($row['last_updated']);
            $items[] = $item;
        }

        $stmt->close();
        return $items;
    }

    /**
     * Сохранить результаты поиска в кэш.
     */
    public function saveResults(array $items): int
    {
        $saved = 0;
        $stmt = $this->db->prepare(
            "INSERT INTO b_supplier_stock 
             (supplier_code, article, brand, brand_normalized, name, price, quantity, 
              warehouse_name, warehouse_code, delivery_days, is_sched, multiplicity, stock_id, last_updated)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
             price = VALUES(price), quantity = VALUES(quantity), 
             name = VALUES(name), last_updated = NOW(), is_active = 1"
        );

        foreach ($items as $item) {
            if (!($item instanceof SearchResultItem)) continue;
            if ($item->price <= 0 && $item->quantity <= 0) continue;

            $brandNorm = BrandNormalizer::normalize($item->brand);
            $whCode = substr(md5($item->warehouse ?? ''), 0, 8);
            $isSched = $item->isSched ? 1 : 0;

            $stmt->bind_param(
                'sssssdissiis',
                $item->source, $item->article, $item->brand, $brandNorm,
                $item->name, $item->price, $item->quantity,
                $item->warehouse, $whCode, $item->deliveryDays,
                $isSched, $item->multiplicity, $item->stockId
            );
            $stmt->execute();
            if ($stmt->affected_rows >= 0) $saved++;
        }

        $stmt->close();
        return $saved;
    }

    /**
     * Очистка старых записей (старше N часов).
     */
    public function cleanExpired(int $hours = 24): int
    {
        $stmt = $this->db->prepare(
            "UPDATE b_supplier_stock SET is_active = 0 
             WHERE last_updated < DATE_SUB(NOW(), INTERVAL ? HOUR)"
        );
        $stmt->bind_param('i', $hours);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
}
