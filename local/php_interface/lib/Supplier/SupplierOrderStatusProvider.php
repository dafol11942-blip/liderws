<?php
namespace Lider\Supplier;

/**
 * Опциональная возможность коннектора — узнать реальный статус ранее заказанной
 * позиции по нашему собственному reference (см. SupplierOrderable::placeOrder()).
 */
interface SupplierOrderStatusProvider
{
    /**
     * @return array<int, array{
     *     order_number: ?string,
     *     state_id: ?string,
     *     state_text: ?string,
     *     stage: string,
     *     expected_date: ?string,
     *     guaranteed_date: ?string,
     *     store_count: ?int,
     *     release_count: ?int,
     *     refusal_count: ?int,
     *     comment: ?string,
     *     raw: array,
     * }>
     * `stage` — общий, поставщико-независимый этап (см. "Реальный заказ у
     * поставщика" в плане): 'ordered' | 'in_transit' | 'ready' | 'refused'.
     * Каждый коннектор сам переводит свой словарь статусов в эту общую шкалу —
     * ни корзина, ни оформление заказа, ни крон опроса статусов не знают
     * деталей конкретного поставщика, только эту общую шкалу.
     */
    public function fetchOrderStatusByReference(string $reference): array;
}
