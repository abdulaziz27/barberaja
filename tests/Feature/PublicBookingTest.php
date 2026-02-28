<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Outlet $outlet;
    private User $staff;
    private Product $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Barbershop Keren',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        // Use withoutGlobalScopes to bypass TenantScope (no auth context in public tests)
        $this->outlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Cikarang',
            'slug' => 'barbershop-cikarang',
            'address' => 'Jl. Cikarang No. 1',
            'require_dp' => false,
            'allow_walkin' => true,
        ]);

        $this->staff = User::query()->create([
            'name' => 'Budi Kapster',
            'email' => 'budi@barbershop.test',
            'password' => 'password',
        ]);

        UserRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->staff->id,
            'role' => 'staff',
        ]);

        $this->service = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Haircut',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    // ─── GET /outlet/{slug} ───────────────────────────────────────────────────

    public function test_public_outlet_page_loads_with_valid_slug(): void
    {
        // URL: barberaja.com/barbershop-cikarang (no /outlet/ prefix)
        $response = $this->get('/barbershop-cikarang');

        $response->assertStatus(200);
        $response->assertSee('Outlet Cikarang');
        $response->assertSee('Haircut');
        $response->assertSee('Budi Kapster');
    }

    public function test_public_outlet_page_returns_404_for_unknown_slug(): void
    {
        $response = $this->get('/slug-yang-tidak-ada');

        $response->assertStatus(404);
    }

    public function test_slug_is_unique_globally(): void
    {
        // Create another tenant with the same slug — should fail at DB level
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Barbershop',
            'subscription_status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Duplicate Outlet',
            'slug' => 'barbershop-cikarang', // same slug — should throw unique constraint violation
        ]);
    }

    // ─── POST /outlet/{slug}/book ─────────────────────────────────────────────

    public function test_booking_via_public_route_succeeds(): void
    {
        $startTime = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        // URL: barberaja.com/barbershop-cikarang/book
        $response = $this->post('/barbershop-cikarang/book', [
            'customer_name' => 'Andi Pelanggan',
            'customer_phone' => '081234567890',
            'product_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'start_time' => $startTime,
        ]);

        $response->assertRedirect('/barbershop-cikarang');
        $response->assertSessionHas('success');

        // Booking should exist in DB
        $this->assertDatabaseHas('bookings', [
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'source' => 'online',
        ]);
    }

    public function test_cannot_book_with_product_from_different_outlet(): void
    {
        // Create another outlet in same tenant
        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Bekasi',
            'slug' => 'barbershop-bekasi',
        ]);

        $otherService = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $otherOutlet->id,
            'name' => 'Shave',
            'type' => Product::TYPE_SERVICE,
            'price' => 40000,
            'duration_minutes' => 20,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $startTime = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->post('/barbershop-cikarang/book', [
            'customer_name' => 'Andi',
            'product_id' => $otherService->id, // product from different outlet
            'staff_id' => $this->staff->id,
            'start_time' => $startTime,
        ]);

        $response->assertSessionHasErrors(['product_id']);
    }

    public function test_cannot_book_with_staff_from_different_outlet(): void
    {
        $otherStaff = User::query()->create([
            'name' => 'Staff Lain',
            'email' => 'other-staff@test.test',
            'password' => 'password',
        ]);

        // otherStaff is NOT assigned to this outlet
        $startTime = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->post('/barbershop-cikarang/book', [
            'customer_name' => 'Andi',
            'product_id' => $this->service->id,
            'staff_id' => $otherStaff->id, // staff not in this outlet
            'start_time' => $startTime,
        ]);

        $response->assertSessionHasErrors(['staff_id']);
    }

    public function test_cannot_access_other_tenant_via_id_injection(): void
    {
        // Create a completely different tenant + outlet
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet',
            'slug' => 'other-outlet-slug',
        ]);

        $otherStaff = User::query()->create([
            'name' => 'Other Staff',
            'email' => 'other@other.test',
            'password' => 'password',
        ]);

        UserRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'outlet_id' => $otherOutlet->id,
            'user_id' => $otherStaff->id,
            'role' => 'staff',
        ]);

        $otherService = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'outlet_id' => $otherOutlet->id,
            'name' => 'Other Service',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $startTime = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        // Try to book on barbershop-cikarang (tenant A) using product/staff from other tenant (tenant B)
        $response = $this->post('/barbershop-cikarang/book', [
            'customer_name' => 'Attacker',
            'product_id' => $otherService->id,  // from other tenant
            'staff_id' => $otherStaff->id,       // from other tenant
            'start_time' => $startTime,
        ]);

        // Should fail validation — product not valid for this outlet
        $response->assertSessionHasErrors(['product_id']);

        // No booking should be created for other tenant
        $this->assertDatabaseMissing('bookings', [
            'tenant_id' => $otherTenant->id,
        ]);
    }

    public function test_booking_requires_customer_name(): void
    {
        $startTime = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->post('/barbershop-cikarang/book', [
            // customer_name missing
            'product_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'start_time' => $startTime,
        ]);

        $response->assertSessionHasErrors(['customer_name']);
    }

    public function test_booking_requires_future_start_time(): void
    {
        $response = $this->post('/barbershop-cikarang/book', [
            'customer_name' => 'Andi',
            'product_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'start_time' => '2020-01-01 10:00:00', // past date
        ]);

        $response->assertSessionHasErrors(['start_time']);
    }
}
