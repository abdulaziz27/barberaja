<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Services\TicketService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosUiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Outlet $outlet;
    private User $owner;
    private User $staff;
    private Product $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Barbershop POS',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $this->outlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet POS',
            'slug' => 'outlet-pos-test',
        ]);

        $this->owner = User::query()->create([
            'name' => 'Owner POS',
            'email' => 'owner-pos@test.test',
            'password' => bcrypt('password'),
        ]);

        UserRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        $this->staff = User::query()->create([
            'name' => 'Staff POS',
            'email' => 'staff-pos@test.test',
            'password' => bcrypt('password'),
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
            'name' => 'Haircut POS',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        TenantContext::set($this->tenant->id);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    private function makeCompletedTicket(): ServiceTicket
    {
        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $ticketService->addItem($ticket, $this->service->id, 1);
        return $ticketService->completeTicket($ticket);
    }

    // ─── Double complete prevention ───────────────────────────────────────────

    public function test_double_complete_via_service_throws(): void
    {
        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $ticketService->addItem($ticket, $this->service->id, 1);
        $ticketService->completeTicket($ticket);

        // Second complete attempt must throw
        $this->expectException(\InvalidArgumentException::class);
        $ticketService->completeTicket($ticket->fresh());
    }

    public function test_double_complete_via_http_returns_error(): void
    {
        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $ticketService->addItem($ticket, $this->service->id, 1);
        $ticketService->completeTicket($ticket);

        // Simulate second HTTP POST to complete
        $response = $this->actingAs($this->owner)
            ->post(route('tickets.complete', $ticket->id));

        // Should redirect back with error (not crash)
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ─── Tenant isolation ─────────────────────────────────────────────────────

    public function test_cannot_add_item_from_different_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet',
            'slug' => 'other-outlet-pos',
        ]);

        $otherProduct = Product::query()->withoutGlobalScopes()->create([
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

        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tenant/i');

        $ticketService->addItem($ticket, $otherProduct->id, 1);
    }

    public function test_cannot_view_ticket_from_different_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant 2',
            'subscription_status' => 'active',
        ]);

        $otherOutlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Outlet 2',
            'slug' => 'other-outlet-pos-2',
        ]);

        $otherStaff = User::query()->create([
            'name' => 'Other Staff',
            'email' => 'other-staff-pos@test.test',
            'password' => bcrypt('password'),
        ]);

        // Create ticket for other tenant (bypass TenantScope)
        TenantContext::set($otherTenant->id);
        $otherTicket = ServiceTicket::query()->create([
            'tenant_id' => $otherTenant->id,
            'outlet_id' => $otherOutlet->id,
            'staff_id' => $otherStaff->id,
            'status' => ServiceTicket::STATUS_OPEN,
            'subtotal' => 0,
            'total' => 0,
            'dp_amount' => 0,
            'payment_status' => ServiceTicket::PAYMENT_STATUS_PENDING,
        ]);
        TenantContext::set($this->tenant->id);

        // Owner from tenant A tries to view ticket from tenant B
        $response = $this->actingAs($this->owner)
            ->get(route('tickets.show', $otherTicket->id));

        // Should return 403 or 404 (policy blocks cross-tenant access)
        $this->assertContains($response->status(), [403, 404]);
    }

    // ─── No duplicate cart submission ─────────────────────────────────────────

    public function test_adding_same_service_twice_creates_two_items(): void
    {
        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);

        // Add same service twice (valid for services — each is a separate line)
        $ticketService->addItem($ticket, $this->service->id, 1);
        $ticketService->addItem($ticket, $this->service->id, 1);

        $ticket->refresh();
        $this->assertCount(2, $ticket->items);
    }

    public function test_complete_button_disabled_when_no_items(): void
    {
        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);

        // canComplete() returns false when no items
        $this->assertFalse($ticket->canComplete());

        // HTTP complete should fail
        $response = $this->actingAs($this->owner)
            ->post(route('tickets.complete', $ticket->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
