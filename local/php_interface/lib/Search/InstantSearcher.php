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
                 WHERE REPLACE(REPLACE(REPLACE(LOWER(article),'-',''),' ',''),'.','') = ? AND brand_normalized = ? AND is_active = 1
                 AND last_updated > NOW() - INTERVAL 4 HOUR
                 ORDER BY is_sched ASC, price ASC"
            );
            $brandNorm = BrandNormalizer::normalize($brand);
            $stmt->bind_param('ss', $article, $brandNorm);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM b_supplier_stock 
                 WHERE REPLACE(REPLACE(REPLACE(LOWER(article),'-',''),' ',''),'.','') = ? AND is_active = 1
                 AND last_updated > NOW() - INTERVAL 4 HOUR
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
            article = VALUES(article), brand = VALUES(brand), brand_normalized = VALUES(brand_normalized),
            price = VALUES(price), quantity = VALUES(quantity), 
            name = VALUES(name), last_updated = NOW(), is_active = 1"
        );

        // FIX TTL: деактивируем старые строки перед вставкой свежих —
        // исчезнувшие позиции не остаются is_active=1 вечно
        $deactivateStmt = $this->db->prepare(
            "UPDATE b_supplier_stock SET is_active = 0
             WHERE supplier_code = ? AND article = ? AND brand_normalized = ?"
        );
        $seenKeys = [];
        foreach ($items as $_itm) {
            if (!($_itm instanceof SearchResultItem)) continue;
            if ($_itm->price <= 0 && $_itm->quantity <= 0) continue;
            $_bn  = BrandNormalizer::normalize($_itm->brand);
            $_key = $_itm->source . '|' . $_itm->article . '|' . $_bn;
            if (!isset($seenKeys[$_key])) {
                $seenKeys[$_key] = true;
                $_an = BrandNormalizer::normalizeArticle($_itm->article);
                $deactivateStmt->bind_param('sss', $_itm->source, $_an, $_bn);
                $deactivateStmt->execute();
            }
        }
        $deactivateStmt->close();
        unset($seenKeys, $_itm, $_bn, $_key);

        foreach ($items as $item) {
            if (!($item instanceof SearchResultItem)) continue;
            if ($item->price <= 0 && $item->quantity <= 0) continue;

            $brandNorm   = BrandNormalizer::normalize($item->brand);
            $articleNorm = BrandNormalizer::normalizeArticle($item->article);
            $whCode  = substr(md5($item->warehouse ?? ''), 0, 8);
            $isSched = $item->isSched ? 1 : 0;
            $multi   = (int)($item->multiplicity ?: 1);
            $delDays = (int)($item->deliveryDays ?: 0);

            // FIX #2: пустой stock_id → коллизия UNIQUE KEY → теряем все строки кроме первой
                $stockId = !empty($item->stockId)
                ? (string)$item->stockId . '|' . $articleNorm . '|' . substr(md5($item->warehouse ?? ''), 0, 6)
                : md5($item->source . '|' . $item->article . '|' . $item->brand . '|' . ($item->warehouse ?? '') . '|' . $item->price);

            $stmt->bind_param(
                'sssssdissiiis',
                $item->source, $articleNorm, $item->brand, $brandNorm,
                $item->name, $item->price, $item->quantity,
                $item->warehouse, $whCode, $delDays,
                $isSched, $multi, $stockId
            );
            if (!$stmt->execute()) {
                error_log('[InstantSearcher] execute failed: ' . $stmt->error .
                    ' | supplier=' . $item->source . ' article=' . $item->article);
                continue;
            }
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
