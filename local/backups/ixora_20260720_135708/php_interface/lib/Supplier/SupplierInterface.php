<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

interface SupplierInterface
{
    public function getCode(): string;
    public function getName(): string;
    public function isAvailable(): bool;

    /** @return SearchResultItem[] */
    public function search(string $query): array;

    /**
     * @return array<int, array{brand: string, article: string, article_nr: string, description: string}>
     */
    public function searchBrands(string $article): array;

    /** @return SearchResultItem[] */
    public function searchByBrandArticle(string $brand, string $article): array;

    public function getDetail(string $article, string $brand): ?SearchResultItem;
    public function getWarehousePrefix(): string;
    public function maskWarehouseName(string $realName): string;

    /** @return array{url: string, headers: array, method: string, body: ?string}|null */
    public function buildBrandsRequest(string $article): ?array;

    /**
     * @param string $requestArticle артикул, с которым строили запрос (важно для PartKom)
     * @return array<int, array{brand: string, article: string, article_nr: string, description: string}>
     */
    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array;

    /** @return array{url: string, headers: array, method: string, body: ?string}|null */
    public function buildSearchRequest(string $brand, string $article): ?array;

    /** @return SearchResultItem[] */
    public function parseSearchResponse(string $responseBody, string $brand, string $article): array;
}
