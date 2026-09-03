<?php
namespace Lider\Supplier;

/**
 * Опциональная возможность коннектора — реально оформить заказ у поставщика.
 * Не часть SupplierInterface: большинство коннекторов её пока не реализуют,
 * order_create_handler.php проверяет `instanceof SupplierOrderable` и просто
 * пропускает тех, кто её не поддерживает.
 */
interface SupplierOrderable
{
    /**
     * @param array<int, array{
     *     article: string,
     *     brand: string,
     *     price_base: float,
     *     quantity: int,
     *     order_meta: array,
     *     reference: string,
     *     comment: string,
     * }> $items Позиции одного поставщика из одного нашего заказа.
     * @return array{http_code: ?int, raw: ?array, error: ?string}
     */
    public function placeOrder(array $items, bool $test = false): array;
}
