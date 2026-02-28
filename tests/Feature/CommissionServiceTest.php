<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\StaffCommission;
use App\Models\StaffProductCommission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\TicketService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Outlet $outlet;
    private User $staff;
    private CommissionService $commissionService;
    private TicketService $ticketService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Barbershop',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $this->outlet = Outlet::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Utama',
            'slug' => 'outlet-utama-commission',
            'require_dp' => false,
            'allow_walkin' => true,
        ]);

        $this->staff = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff-commission@test.test',
            'password' => 'password',
        ]);

        TenantContext::set($this->tenant->id);

        $this->commissionService = app(CommissionService::class);
        $this->ticketService = app(TicketService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeProduct(string $name, float $price, string $commissionType, float $commissionValue): Product
    {
        return Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'name' => $name,
            'type' => Product::TYPE_SERVICE,
            'price' => $price,
            'duration_minutes' => 30,
            'stock_qty' => null,
            'commission_type' => $commissionType,
            'commission_value' => $commissionValue,
            'is_active' => true,
        ]);
    }

    private function completeTicketWith(Product $product, int $qty = 1): ServiceTicket
    {
        $ticket = $this->ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $this->ticketService->addItem($ticket, $product->id, $qty);
        return $this->ticketService->completeTicket($ticket);
    }

    // ─── Percentage commission ─────────────────────────────────────────────────

    public function test_percentage_commission_calculated_correctly(): void
    {
        // 10% of Rp 100.000 = Rp 10.000
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);

        $ticket = $this->completeTicketWith($product, 1);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(10000.00, (float) $commission->amount);
        $this->assertSame($this->staff->id, $commission->staff_id);
    }

    public function test_percentage_commission_with_qty(): void
    {
        // 10% of (Rp 50.000 × 2) = Rp 10.000
        $product = $this->makeProduct('Haircut', 50000, Product::COMMISSION_PERCENTAGE, 10);

        $ticket = $this->completeTicketWith($product, 2);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(10000.00, (float) $commission->amount);
    }

    // ─── Fixed commission ──────────────────────────────────────────────────────

    public function test_fixed_commission_calculated_correctly(): void
    {
        // Fixed Rp 15.000 per item, qty=1 → Rp 15.000
        $product = $this->makeProduct('Shave', 80000, Product::COMMISSION_FIXED, 15000);

        $ticket = $this->completeTicketWith($product, 1);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(15000.00, (float) $commission->amount);
    }

    public function test_fixed_commission_with_qty(): void
    {
        // Fixed Rp 15.000 per item, qty=3 → Rp 45.000
        $product = $this->makeProduct('Shave', 80000, Product::COMMISSION_FIXED, 15000);

        $ticket = $this->completeTicketWith($product, 3);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(45000.00, (float) $commission->amount);
    }

    // ─── None commission ───────────────────────────────────────────────────────

    public function test_none_commission_results_in_zero(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_NONE, 0);

        $ticket = $this->completeTicketWith($product, 1);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(0.00, (float) $commission->amount);
    }

    // ─── Override commission ───────────────────────────────────────────────────

    public function test_override_commission_takes_priority_over_product_default(): void
    {
        // Product default: 10% of Rp 100.000 = Rp 10.000
        // Override: fixed Rp 20.000
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);

        $this->commissionService->setStaffOverride(
            $this->tenant->id,
            $this->staff->id,
            $product->id,
            Product::COMMISSION_FIXED,
            20000
        );

        $ticket = $this->completeTicketWith($product, 1);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(20000.00, (float) $commission->amount);

        // Verify breakdown marks it as overridden
        $this->assertTrue($commission->breakdown[0]['overridden']);
    }

    public function test_override_percentage_works(): void
    {
        // Product default: none
        // Override: 15% of Rp 100.000 = Rp 15.000
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_NONE, 0);

        $this->commissionService->setStaffOverride(
            $this->tenant->id,
            $this->staff->id,
            $product->id,
            Product::COMMISSION_PERCENTAGE,
            15
        );

        $ticket = $this->completeTicketWith($product, 1);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(15000.00, (float) $commission->amount);
    }

    public function test_remove_override_reverts_to_product_default(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);

        $this->commissionService->setStaffOverride(
            $this->tenant->id,
            $this->staff->id,
            $product->id,
            Product::COMMISSION_FIXED,
            20000
        );

        $this->commissionService->removeStaffOverride($this->staff->id, $product->id);

        $ticket = $this->completeTicketWith($product, 1);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(10000.00, (float) $commission->amount); // back to 10%
        $this->assertFalse($commission->breakdown[0]['overridden']);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_commission_calculated_only_once_per_ticket(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);

        $ticket = $this->completeTicketWith($product, 1);

        // Call calculateCommission again on the same completed ticket
        $commission1 = StaffCommission::query()->where('ticket_id', $ticket->id)->first();
        $commission2 = $this->commissionService->calculateCommission($ticket->fresh());

        $this->assertSame($commission1->id, $commission2->id);
        $this->assertEquals(1, StaffCommission::query()->where('ticket_id', $ticket->id)->count());
    }

    // ─── Plan does not affect commission ──────────────────────────────────────

    public function test_free_plan_commission_same_as_pro_plan(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);

        // Free plan
        $this->tenant->update(['plan_type' => Tenant::PLAN_TYPE_FREE]);
        $ticketFree = $this->completeTicketWith($product, 1);
        $commissionFree = StaffCommission::query()->where('ticket_id', $ticketFree->id)->first();

        // Pro plan
        $this->tenant->update(['plan_type' => Tenant::PLAN_TYPE_PRO]);
        $ticketPro = $this->completeTicketWith($product, 1);
        $commissionPro = StaffCommission::query()->where('ticket_id', $ticketPro->id)->first();

        $this->assertEquals((float) $commissionFree->amount, (float) $commissionPro->amount);
        $this->assertEquals(10000.00, (float) $commissionFree->amount);
    }

    // ─── Multi-item ticket ─────────────────────────────────────────────────────

    public function test_commission_summed_across_multiple_items(): void
    {
        // Haircut: 10% of Rp 100.000 = Rp 10.000
        // Shave: fixed Rp 5.000
        // Total: Rp 15.000
        $haircut = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);
        $shave = $this->makeProduct('Shave', 80000, Product::COMMISSION_FIXED, 5000);

        $ticket = $this->ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $this->ticketService->addItem($ticket, $haircut->id, 1);
        $this->ticketService->addItem($ticket, $shave->id, 1);
        $ticket = $this->ticketService->completeTicket($ticket);

        $commission = StaffCommission::query()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($commission);
        $this->assertEquals(15000.00, (float) $commission->amount);
        $this->assertCount(2, $commission->breakdown);
    }

    // ─── calculateCommission requires completed ticket ─────────────────────────

    public function test_calculate_commission_throws_for_non_completed_ticket(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_PERCENTAGE, 10);

        $ticket = $this->ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $this->ticketService->addItem($ticket, $product->id, 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/completed/i');

        $this->commissionService->calculateCommission($ticket->fresh());
    }

    // ─── setStaffOverride validation ──────────────────────────────────────────

    public function test_set_override_with_invalid_commission_type_throws(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_NONE, 0);

        $this->expectException(\InvalidArgumentException::class);

        $this->commissionService->setStaffOverride(
            $this->tenant->id,
            $this->staff->id,
            $product->id,
            'invalid_type',
            10
        );
    }

    public function test_set_override_with_negative_value_throws(): void
    {
        $product = $this->makeProduct('Haircut', 100000, Product::COMMISSION_NONE, 0);

        $this->expectException(\InvalidArgumentException::class);

        $this->commissionService->setStaffOverride(
            $this->tenant->id,
            $this->staff->id,
            $product->id,
            Product::COMMISSION_FIXED,
            -100
        );
    }
}
