<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\Tenant;
use App\Services\BundleService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Outlet $outlet;
    private BundleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Barbershop',
            'subscription_status' => 'active',
        ]);

        $this->outlet = Outlet::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Utama',
            'slug' => 'outlet-utama',
        ]);

        TenantContext::set($this->tenant->id);

        $this->service = app(BundleService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeService(string $name, int $duration, ?string $outletId = null): Product
    {
        return Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outletId ?? $this->outlet->id,
            'name' => $name,
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => $duration,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);
    }

    // ─── createBundle ─────────────────────────────────────────────────────────

    public function test_create_bundle_succeeds(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
            'commission_type' => Product::COMMISSION_PERCENTAGE,
            'commission_value' => 10,
        ]);

        $this->assertInstanceOf(Product::class, $bundle);
        $this->assertSame(Product::TYPE_BUNDLE, $bundle->type);
        $this->assertSame($this->tenant->id, $bundle->tenant_id);
        $this->assertSame('Paket Gold', $bundle->name);
        $this->assertEquals(0, $bundle->duration_minutes); // no items yet
    }

    public function test_create_bundle_requires_tenant_context(): void
    {
        TenantContext::set(null);

        $this->expectException(\RuntimeException::class);
        $this->service->createBundle([
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);
    }

    // ─── addServiceToBundle ───────────────────────────────────────────────────

    public function test_add_service_to_bundle_calculates_duration(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);
        $shave = $this->makeService('Shave', 20);

        $this->service->addServiceToBundle($bundle, $haircut);
        $this->service->addServiceToBundle($bundle, $shave);

        $bundle->refresh();
        $this->assertEquals(50, $bundle->duration_minutes); // 30 + 20
    }

    public function test_add_service_with_qty_multiplies_duration(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Platinum',
            'price' => 200000,
        ]);

        $haircut = $this->makeService('Haircut', 30);

        $this->service->addServiceToBundle($bundle, $haircut, 2);

        $bundle->refresh();
        $this->assertEquals(60, $bundle->duration_minutes); // 30 × 2
    }

    public function test_cannot_add_bundle_into_bundle(): void
    {
        $bundle1 = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $bundle2 = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Platinum',
            'price' => 200000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/service/i');

        $this->service->addServiceToBundle($bundle1, $bundle2);
    }

    public function test_cannot_add_same_service_twice(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);

        $this->service->addServiceToBundle($bundle, $haircut);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->addServiceToBundle($bundle, $haircut);
    }

    public function test_cannot_add_service_from_different_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Barbershop',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet',
            'slug' => 'other-outlet',
        ]);

        // Create service for other tenant (bypass TenantScope)
        $foreignService = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'outlet_id' => $otherOutlet->id,
            'name' => 'Foreign Haircut',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tenant/i');

        $this->service->addServiceToBundle($bundle, $foreignService);
    }

    public function test_tenant_isolation_enforced_on_bundle(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Barbershop',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet',
            'slug' => 'other-outlet-2',
        ]);

        // Create bundle for other tenant (bypass TenantScope)
        $foreignBundle = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'outlet_id' => $otherOutlet->id,
            'name' => 'Foreign Bundle',
            'type' => Product::TYPE_BUNDLE,
            'price' => 150000,
            'duration_minutes' => 0,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $service = $this->makeService('Haircut', 30);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tenant/i');

        $this->service->addServiceToBundle($foreignBundle, $service);
    }

    public function test_add_service_requires_qty_at_least_one(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->addServiceToBundle($bundle, $haircut, 0);
    }

    // ─── removeServiceFromBundle ──────────────────────────────────────────────

    public function test_remove_service_from_bundle_recalculates_duration(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);
        $shave = $this->makeService('Shave', 20);

        $this->service->addServiceToBundle($bundle, $haircut);
        $this->service->addServiceToBundle($bundle, $shave);

        $bundle->refresh();
        $this->assertEquals(50, $bundle->duration_minutes);

        $this->service->removeServiceFromBundle($bundle, $haircut);

        $bundle->refresh();
        $this->assertEquals(20, $bundle->duration_minutes);
    }

    public function test_remove_nonexistent_service_throws(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->removeServiceFromBundle($bundle, $haircut);
    }

    // ─── getBundleItems ───────────────────────────────────────────────────────

    public function test_get_bundle_items_returns_correct_items(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);
        $shave = $this->makeService('Shave', 20);

        $this->service->addServiceToBundle($bundle, $haircut);
        $this->service->addServiceToBundle($bundle, $shave);

        $items = $this->service->getBundleItems($bundle);

        $this->assertCount(2, $items);
        $productIds = $items->pluck('product_id')->sort()->values()->toArray();
        $this->assertContains($haircut->id, $productIds);
        $this->assertContains($shave->id, $productIds);
    }

    public function test_get_bundle_items_throws_for_non_bundle(): void
    {
        $service = $this->makeService('Haircut', 30);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->getBundleItems($service);
    }

    // ─── duration auto-calculated ─────────────────────────────────────────────

    public function test_duration_is_zero_when_bundle_has_no_items(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Kosong',
            'price' => 100000,
        ]);

        $this->assertEquals(0, $bundle->duration_minutes);
    }

    public function test_duration_recalculated_after_all_items_removed(): void
    {
        $bundle = $this->service->createBundle([
            'outlet_id' => $this->outlet->id,
            'name' => 'Paket Gold',
            'price' => 150000,
        ]);

        $haircut = $this->makeService('Haircut', 30);
        $this->service->addServiceToBundle($bundle, $haircut);

        $bundle->refresh();
        $this->assertEquals(30, $bundle->duration_minutes);

        $this->service->removeServiceFromBundle($bundle, $haircut);

        $bundle->refresh();
        $this->assertEquals(0, $bundle->duration_minutes);
    }
}
