<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class BundleService
{
    /**
     * Create a bundle product.
     *
     * Rules:
     * - type is forced to 'bundle'
     * - duration_minutes is NOT set here; it is derived from child services
     * - price is set explicitly by the owner (not derived from children)
     * - commission_type / commission_value apply to the bundle price
     *
     * @param  array{
     *   outlet_id?: string|null,
     *   name: string,
     *   price: numeric-string|float|int,
     *   commission_type?: string,
     *   commission_value?: numeric-string|float|int,
     *   is_active?: bool,
     * } $data
     */
    public function createBundle(array $data): Product
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new \RuntimeException('Tenant context tidak tersedia.');
        }

        return DB::transaction(function () use ($data, $tenantId): Product {
            $bundle = Product::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $data['outlet_id'] ?? null,
                'name' => $data['name'],
                'type' => Product::TYPE_BUNDLE,
                'price' => $data['price'],
                'duration_minutes' => 0, // akan di-recalculate saat item ditambahkan
                'stock_qty' => null,
                'commission_type' => $data['commission_type'] ?? Product::COMMISSION_NONE,
                'commission_value' => $data['commission_value'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $bundle;
        });
    }

    /**
     * Add a service product to a bundle.
     *
     * Constraints:
     * - bundle must be type=bundle and belong to current tenant
     * - product must be type=service and belong to the same tenant
     * - product cannot be a bundle (no nested bundles)
     * - product cannot already be in this bundle
     * - duration_minutes of bundle is recalculated after adding
     *
     * @param  int|positive-int  $qty
     */
    public function addServiceToBundle(Product $bundle, Product $service, int $qty = 1): ProductBundleItem
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new \RuntimeException('Tenant context tidak tersedia.');
        }

        $this->assertBundleBelongsToTenant($bundle, $tenantId);
        $this->assertServiceBelongsToTenant($service, $tenantId);

        if (! $bundle->isBundle()) {
            throw new \InvalidArgumentException('Produk bukan bundle.');
        }

        if (! $service->isService()) {
            throw new \InvalidArgumentException('Hanya produk bertipe service yang dapat ditambahkan ke bundle. Nested bundle tidak diperbolehkan.');
        }

        if ($qty < 1) {
            throw new \InvalidArgumentException('Qty harus minimal 1.');
        }

        return DB::transaction(function () use ($bundle, $service, $qty): ProductBundleItem {
            // Cek apakah service sudah ada di bundle ini
            $existing = ProductBundleItem::query()
                ->where('bundle_id', $bundle->id)
                ->where('product_id', $service->id)
                ->first();

            if ($existing !== null) {
                throw new \InvalidArgumentException("Layanan '{$service->name}' sudah ada di bundle ini.");
            }

            $item = ProductBundleItem::query()->create([
                'bundle_id' => $bundle->id,
                'product_id' => $service->id,
                'qty' => $qty,
            ]);

            $this->recalculateBundleDuration($bundle);

            return $item->fresh(['product']);
        });
    }

    /**
     * Remove a service from a bundle.
     * Duration is recalculated after removal.
     */
    public function removeServiceFromBundle(Product $bundle, Product $service): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new \RuntimeException('Tenant context tidak tersedia.');
        }

        $this->assertBundleBelongsToTenant($bundle, $tenantId);

        if (! $bundle->isBundle()) {
            throw new \InvalidArgumentException('Produk bukan bundle.');
        }

        DB::transaction(function () use ($bundle, $service): void {
            $deleted = ProductBundleItem::query()
                ->where('bundle_id', $bundle->id)
                ->where('product_id', $service->id)
                ->delete();

            if ($deleted === 0) {
                throw new \InvalidArgumentException("Layanan '{$service->name}' tidak ditemukan di bundle ini.");
            }

            $this->recalculateBundleDuration($bundle);
        });
    }

    /**
     * Recalculate and persist bundle duration_minutes = SUM(child.duration_minutes * qty).
     * Called after any add/remove operation.
     * Uses a direct query update to avoid triggering model events or timestamp changes.
     */
    public function recalculateBundleDuration(Product $bundle): void
    {
        $items = ProductBundleItem::query()
            ->where('bundle_id', $bundle->id)
            ->with('product')
            ->get();

        $totalMinutes = $items->sum(fn ($item) => ($item->product->duration_minutes ?? 0) * $item->qty);

        // Direct query update: avoids model events and does not touch updated_at
        Product::query()
            ->withoutGlobalScopes()
            ->whereKey($bundle->id)
            ->update(['duration_minutes' => $totalMinutes]);

        $bundle->duration_minutes = $totalMinutes;
    }

    /**
     * Get all items of a bundle with their child products loaded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductBundleItem>
     */
    public function getBundleItems(Product $bundle): \Illuminate\Database\Eloquent\Collection
    {
        if (! $bundle->isBundle()) {
            throw new \InvalidArgumentException('Produk bukan bundle.');
        }

        return ProductBundleItem::query()
            ->where('bundle_id', $bundle->id)
            ->with('product')
            ->get();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function assertBundleBelongsToTenant(Product $bundle, string $tenantId): void
    {
        if ($bundle->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Bundle tidak ditemukan di tenant ini.');
        }
    }

    private function assertServiceBelongsToTenant(Product $service, string $tenantId): void
    {
        if ($service->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Layanan tidak ditemukan di tenant ini.');
        }
    }
}
