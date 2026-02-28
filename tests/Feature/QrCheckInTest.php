<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCheckInTest extends TestCase
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
            'name' => 'Barbershop QR',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $this->outlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet QR Test',
            'slug' => 'barbershop-qr',
            'require_dp' => false,
            'allow_walkin' => true,
        ]);

        $this->staff = User::query()->create([
            'name' => 'Kapster QR',
            'email' => 'kapster-qr@test.test',
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

    // ─── GET /{slug}/checkin ──────────────────────────────────────────────────

    public function test_checkin_page_loads_with_valid_slug(): void
    {
        $response = $this->get('/barbershop-qr/checkin');

        $response->assertStatus(200);
        $response->assertSee('Outlet QR Test');
        $response->assertSee('Haircut');
        $response->assertSee('Self Check-in');
    }

    public function test_checkin_page_returns_404_for_unknown_slug(): void
    {
        $response = $this->get('/slug-tidak-ada/checkin');

        $response->assertStatus(404);
    }

    // ─── POST /{slug}/checkin ─────────────────────────────────────────────────

    public function test_walk_in_booking_created_with_source_qr(): void
    {
        $response = $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Andi Walk-in',
            'customer_phone' => '081234567890',
            'product_id' => $this->service->id,
        ]);

        $response->assertRedirect('/barbershop-qr/checkin');
        $response->assertSessionHas('success');

        // Booking should exist with source = qr
        $this->assertDatabaseHas('bookings', [
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'source' => 'qr',
            'customer_name' => 'Andi Walk-in',
        ]);
    }

    public function test_booking_source_is_recorded_as_qr(): void
    {
        $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Andi',
            'product_id' => $this->service->id,
        ]);

        $booking = Booking::query()
            ->withoutGlobalScopes()
            ->where('outlet_id', $this->outlet->id)
            ->where('source', 'qr')
            ->first();

        $this->assertNotNull($booking);
        $this->assertSame('qr', $booking->source);
    }

    public function test_cannot_bypass_tenant_via_product_from_other_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet',
            'slug' => 'other-outlet-qr',
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

        $response = $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Attacker',
            'product_id' => $otherService->id, // from other tenant
        ]);

        $response->assertSessionHasErrors(['product_id']);

        // No booking for other tenant
        $this->assertDatabaseMissing('bookings', [
            'tenant_id' => $otherTenant->id,
        ]);
    }

    public function test_double_scan_does_not_create_duplicate_booking_at_same_time(): void
    {
        // First check-in
        $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Andi',
            'product_id' => $this->service->id,
        ]);

        // Second check-in immediately after — should get a different slot (not same time)
        $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Budi',
            'product_id' => $this->service->id,
        ]);

        $bookings = Booking::query()
            ->withoutGlobalScopes()
            ->where('outlet_id', $this->outlet->id)
            ->where('source', 'qr')
            ->orderBy('start_time')
            ->get();

        $this->assertCount(2, $bookings);

        // Second booking must start at or after first booking ends
        $firstEnd = \Carbon\Carbon::parse($bookings[0]->end_time);
        $secondStart = \Carbon\Carbon::parse($bookings[1]->start_time);

        $this->assertTrue(
            $secondStart->gte($firstEnd),
            "Second booking ({$secondStart}) should start at or after first booking ends ({$firstEnd})"
        );
    }

    public function test_checkin_requires_customer_name(): void
    {
        $response = $this->post('/barbershop-qr/checkin', [
            // customer_name missing
            'product_id' => $this->service->id,
        ]);

        $response->assertSessionHasErrors(['customer_name']);
    }

    public function test_checkin_requires_valid_product(): void
    {
        $response = $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Andi',
            'product_id' => 'invalid-uuid-here',
        ]);

        $response->assertSessionHasErrors(['product_id']);
    }

    public function test_overlap_validation_is_not_bypassed(): void
    {
        // Fill up the only staff's slot manually
        TenantContext::set($this->tenant->id);

        $now = \Carbon\Carbon::now();

        // Create a booking that fills the next 2 hours for the only staff
        Booking::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => $now,
            'end_time' => $now->copy()->addHours(2),
            'duration_minutes' => 120,
            'source' => 'kasir',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
        ]);

        TenantContext::set(null);

        // Walk-in should still succeed — it will be queued after the 2-hour block
        $response = $this->post('/barbershop-qr/checkin', [
            'customer_name' => 'Andi',
            'product_id' => $this->service->id,
        ]);

        $response->assertRedirect('/barbershop-qr/checkin');
        $response->assertSessionHas('success');

        // The new booking should start at or after the 2-hour block ends
        $newBooking = Booking::query()
            ->withoutGlobalScopes()
            ->where('outlet_id', $this->outlet->id)
            ->where('source', 'qr')
            ->first();

        $this->assertNotNull($newBooking);
        $this->assertTrue(
            \Carbon\Carbon::parse($newBooking->start_time)->gte($now->copy()->addHours(2))
        );
    }
}
