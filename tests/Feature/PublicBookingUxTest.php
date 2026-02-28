<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingUxTest extends TestCase
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
            'name' => 'Barbershop UX',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $this->outlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet UX Test',
            'slug' => 'barbershop-ux',
            'require_dp' => false,
            'allow_walkin' => true,
        ]);

        $this->staff = User::query()->create([
            'name' => 'Kapster UX',
            'email' => 'kapster-ux@test.test',
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
            'name' => 'Haircut UX',
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

    // ─── GET /{slug} ──────────────────────────────────────────────────────────

    public function test_public_booking_page_loads(): void
    {
        $response = $this->get('/barbershop-ux');

        $response->assertStatus(200);
        $response->assertSee('Outlet UX Test');
        $response->assertSee('Haircut UX');
        $response->assertSee('Kapster UX');
    }

    public function test_invalid_slug_returns_404(): void
    {
        $response = $this->get('/slug-tidak-ada-sama-sekali');

        $response->assertStatus(404);
    }

    // ─── GET /{slug}/slots ────────────────────────────────────────────────────

    public function test_slots_api_returns_json_for_valid_request(): void
    {
        $date = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->getJson("/barbershop-ux/slots?product_id={$this->service->id}&staff_id={$this->staff->id}&date={$date}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['slots']);
        $this->assertIsArray($response->json('slots'));
    }

    public function test_slots_api_returns_empty_for_past_date(): void
    {
        $date = Carbon::yesterday()->format('Y-m-d');

        $response = $this->getJson("/barbershop-ux/slots?product_id={$this->service->id}&date={$date}");

        $response->assertStatus(200);
        $response->assertJson(['slots' => []]);
    }

    public function test_slots_api_rejects_product_from_other_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet',
            'slug' => 'other-outlet-ux',
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

        $date = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->getJson("/barbershop-ux/slots?product_id={$otherService->id}&date={$date}");

        $response->assertStatus(422);
    }

    public function test_slots_api_marks_booked_slots_as_unavailable(): void
    {
        TenantContext::set($this->tenant->id);

        $tomorrow = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);

        // Create a booking at 10:00 for 30 minutes
        Booking::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => $tomorrow,
            'end_time' => $tomorrow->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'source' => 'kasir',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
        ]);

        TenantContext::set(null);

        $date = $tomorrow->format('Y-m-d');
        $response = $this->getJson("/barbershop-ux/slots?product_id={$this->service->id}&staff_id={$this->staff->id}&date={$date}");

        $response->assertStatus(200);
        $slots = $response->json('slots');

        // Find the 10:00 slot — should be unavailable
        $slot10 = collect($slots)->firstWhere('time', '10:00');
        $this->assertNotNull($slot10);
        $this->assertFalse($slot10['available']);
    }

    // ─── POST /{slug}/book ────────────────────────────────────────────────────

    public function test_booking_succeeds_with_valid_data(): void
    {
        $startTime = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->post('/barbershop-ux/book', [
            'customer_name' => 'Andi UX',
            'customer_phone' => '081234567890',
            'product_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'start_time' => $startTime,
        ]);

        $response->assertRedirect('/barbershop-ux');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'outlet_id' => $this->outlet->id,
            'customer_name' => 'Andi UX',
            'source' => 'online',
        ]);
    }

    public function test_cannot_book_other_tenant_via_product_injection(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant UX',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet UX',
            'slug' => 'other-outlet-ux-2',
        ]);

        $otherService = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'outlet_id' => $otherOutlet->id,
            'name' => 'Other Service UX',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $startTime = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->post('/barbershop-ux/book', [
            'customer_name' => 'Attacker',
            'product_id' => $otherService->id, // from other tenant
            'staff_id' => $this->staff->id,
            'start_time' => $startTime,
        ]);

        $response->assertSessionHasErrors(['product_id']);

        $this->assertDatabaseMissing('bookings', [
            'tenant_id' => $otherTenant->id,
        ]);
    }

    public function test_slot_conflict_handled_gracefully(): void
    {
        TenantContext::set($this->tenant->id);

        $startTime = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);

        // Pre-fill the slot
        Booking::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'source' => 'kasir',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
        ]);

        TenantContext::set(null);

        // Try to book the same slot
        $response = $this->post('/barbershop-ux/book', [
            'customer_name' => 'Andi',
            'product_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'start_time' => $startTime->format('Y-m-d H:i:s'),
        ]);

        // Should redirect back with error (not crash)
        $response->assertRedirect();
        $response->assertSessionHasErrors(['start_time']);
    }

    public function test_dp_mode_respected_in_view(): void
    {
        // Update outlet to require DP
        $this->outlet->update(['require_dp' => true]);

        $response = $this->get('/barbershop-ux');

        $response->assertStatus(200);
        $response->assertSee('DP'); // Should show DP indicator
    }
}
