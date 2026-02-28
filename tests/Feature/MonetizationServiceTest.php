<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\MonetizationService;
use App\Services\TicketService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $staff;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $this->outlet = Outlet::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet 1',
            'slug' => 'outlet-1',
            'require_dp' => false,
            'allow_walkin' => true,
        ]);

        $this->staff = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff@test.test',
            'password' => 'password',
        ]);

        $this->product = Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Service',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'stock_qty' => null,
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

    public function test_free_plan_applies_transaction_fee_on_ticket_completion(): void
    {
        $this->tenant->update(['plan_type' => Tenant::PLAN_TYPE_FREE]);
        TenantContext::set($this->tenant->id);

        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $ticketService->addItem($ticket, $this->product->id, 1);
        $ticketService->completeTicket($ticket);

        $entry = LedgerEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_id', 'ticket_fee:'.$ticket->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertCount(2, $entry->lines);
        $debit = (float) $entry->lines->where('direction', LedgerLine::DIRECTION_DEBIT)->sum('amount');
        $credit = (float) $entry->lines->where('direction', LedgerLine::DIRECTION_CREDIT)->sum('amount');
        $this->assertEquals(2000, $debit);
        $this->assertEquals(2000, $credit);
    }

    public function test_pro_plan_applies_no_transaction_fee_on_ticket_completion(): void
    {
        $this->tenant->update(['plan_type' => Tenant::PLAN_TYPE_PRO]);
        TenantContext::set($this->tenant->id);

        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $ticketService->addItem($ticket, $this->product->id, 1);
        $ticketService->completeTicket($ticket);

        $entry = LedgerEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_id', 'ticket_fee:'.$ticket->id)
            ->first();

        $this->assertNull($entry);
    }

    public function test_subscription_deduction_creates_ledger_when_balance_sufficient(): void
    {
        $this->tenant->update(['plan_type' => Tenant::PLAN_TYPE_PRO]);
        TenantContext::set($this->tenant->id);

        app(LedgerService::class)->createBalancedEntry(
            'seed_escrow',
            [
                ['account' => LedgerService::ACCOUNT_CLEARING, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => 100000],
                ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => 100000],
            ],
            'Seed escrow'
        );

        TenantContext::set(null);
        $monetization = app(MonetizationService::class);
        $this->tenant->refresh();
        $result = $monetization->deductSubscription($this->tenant);

        $this->assertTrue($result);

        $ref = 'subscription:'.$this->tenant->id.':'.now()->format('Y-m');
        $entry = LedgerEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_id', $ref)
            ->first();

        $this->assertNotNull($entry);
        $credit = (float) $entry->lines->where('account', LedgerService::ACCOUNT_SUBSCRIPTION_REVENUE)->sum('amount');
        $this->assertEquals(99000, $credit);
    }

    public function test_subscription_deduction_marks_overdue_when_balance_insufficient(): void
    {
        $this->tenant->update(['plan_type' => Tenant::PLAN_TYPE_PRO]);
        $this->tenant->update(['subscription_overdue_at' => null]);

        $monetization = app(MonetizationService::class);
        $result = $monetization->deductSubscription($this->tenant);

        $this->assertFalse($result);
        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->subscription_overdue_at);
    }
}
