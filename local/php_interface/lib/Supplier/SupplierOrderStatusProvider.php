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
     *     expected_date: ?string,
     *     guaranteed_date: ?string,
     *     store_count: ?int,
     *     release_count: ?int,
     *     refusal_count: ?int,
     *     comment: ?string,
     *     raw: array,
     * }>
     */
    public function fetchOrderStatusByReference(string $reference): array;
}
