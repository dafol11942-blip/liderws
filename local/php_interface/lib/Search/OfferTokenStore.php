<?php
namespace Lider\Search;

/**
 * Короткоживущее хранилище «токен предложения → реальные данные» (склад, поставщик,
 * закупочная цена, остаток на момент поиска). Небезопасные для клиента поля (реальное
 * название склада, код поставщика) никогда не уходят в JSON поиска для не-менеджеров —
 * вместо них фронтенд получает только токен, а настоящие данные восстанавливаются
 * здесь на бэкенде в момент добавления в корзину (см. order_from_supplier.php).
 *
 * Хранится в temp-файле по taskId, по аналогии с progress/dedup файлами search/ajax.php.
 */
class OfferTokenStore
{
    private const TTL_SECONDS = 6 * 3600;

    private static function path(string $taskId): string
    {
        return sys_get_temp_dir() . '/srch_whmap_' . preg_replace('/[^a-zA-Z0-9_]/', '', $taskId) . '.json';
    }

    /** @param array<string,array{supplier:string,warehouse:string,price:float,quantity:int}> $tokens */
    public static function save(string $taskId, array $tokens): void
    {
        if ($taskId === '' || empty($tokens)) {
            return;
        }
        $file = self::path($taskId);
        $existing = [];
        if (is_file($file)) {
            $existing = json_decode((string)@file_get_contents($file), true) ?: [];
        }
        @file_put_contents($file, json_encode($tokens + $existing, JSON_UNESCAPED_UNICODE));
    }

    /** @return array{supplier:string,warehouse:string,price:float,quantity:int}|null */
    public static function resolve(string $taskId, string $token): ?array
    {
        if ($taskId === '' || $token === '') {
            return null;
        }
        $file = self::path($taskId);
        if (!is_file($file) || (time() - filemtime($file)) > self::TTL_SECONDS) {
            return null;
        }
        $map = json_decode((string)@file_get_contents($file), true);
        return is_array($map) && isset($map[$token]) ? $map[$token] : null;
    }
}
