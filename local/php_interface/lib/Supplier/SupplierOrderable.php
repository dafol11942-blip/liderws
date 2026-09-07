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
     * @return array{http_code: ?int, success: bool, raw: ?array, error: ?string, item_references?: array<int,string>}
     * `item_references` — опционально: [basket_item_id => reference], если
     * поставщик не поддерживает наш собственный reference (см. ПартКом —
     * $item['reference']) и для последующего опроса статуса нужен другой ключ
     * (напр. Москворечье — их order_number, известный только после ответа
     * /orders). Если отдано — dispatchSupplierOrders() использует его вместо
     * дефолтного {orderId}_{basketItemId} при сохранении в b_supplier_order_item.
     */
    public function placeOrder(array $items, bool $test = false): array;
}
