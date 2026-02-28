<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * List services (type=service only) for current tenant.
     */
    public function listServices(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->services()
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a service product. Validates duration_minutes when type=service.
     */
    public function createService(array $data): Product
    {
        $data['type'] = Product::TYPE_SERVICE;
        $data['stock_qty'] = null;
        if (empty($data['duration_minutes']) || (int) $data['duration_minutes'] < 1) {
            throw new \InvalidArgumentException('Layanan wajib memiliki durasi (menit) minimal 1.');
        }

        return Product::query()->create($data);
    }

    /**
     * Update a service product. Validates duration_minutes when type=service.
     */
    public function updateService(Product $product, array $data): Product
    {
        if ($product->type !== Product::TYPE_SERVICE) {
            throw new \InvalidArgumentException('Hanya produk layanan yang dapat diedit di sini.');
        }
        if (isset($data['duration_minutes']) && (int) $data['duration_minutes'] < 1) {
            throw new \InvalidArgumentException('Layanan wajib memiliki durasi (menit) minimal 1.');
        }

        $product->update($data);

        return $product->fresh();
    }

    /**
     * Delete (soft) a product.
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }
}
