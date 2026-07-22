<?php
namespace Lider\Supplier;

class SupplierFactory
{
    private array $suppliers = [];

    public function register(SupplierInterface $supplier): void
    {
        $this->suppliers[$supplier->getCode()] = $supplier;
    }

    public function all(): array
    {
        return $this->suppliers;
    }

    public function get(string $code): ?SupplierInterface
    {
        return $this->suppliers[$code] ?? null;
    }

    public function allAvailable(): array
    {
        return array_filter($this->suppliers, fn($s) => $s->isAvailable());
    }
}
