<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;
use App\Services\TicketService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailStockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Outlet $outlet;
    private User $staff;
    private StockService $stockService;
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
            'slug' => 'outlet-utama-retail',
            'require_dp' => false,
            'allow_walkin' => true,
        ]);

        $this->staff = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff-retail@test.test',
            'password' => 'password',
        ]);

        TenantContext::set($this->tenant->id);

        $this->stockService = app(StockService::class);
        $this->ticketService = app(TicketService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeRetailProduct(string $name, int $stock): Product
    {
        return Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'name' => $name,
            'type' => Product::TYPE_RETAIL,
            'price' => 50000,
            'duration_minutes' => null,
            'stock_qty' => $stock,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);
    }

    private function makeServiceProduct(string $name): Product
    {
        return Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'name' => $name,
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'stock_qty' => null,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);
    }

    private function makeTicketWithItem(Product $product, int $qty = 1): ServiceTicket
    {
        $ticket = $this->ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $this->ticketService->addItem($ticket, $product->id, $qty);
        return $ticket->fresh();
    }

    // ─── StockService::decreaseStock ──────────────────────────────────────────

    public function test_decrease_stock_reduces_qty_correctly(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pomade) {
            $this->stockService->decreaseStock($pomade, 3);
        });

        $pomade->refresh();
        $this->assertEquals(7, $pomade->stock_qty);
    }

    public function test_decrease_stock_writes_audit_log(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pomade) {
            $this->stockService->decreaseStock($pomade, 2, 'ticket-123');
        });

        $log = AuditLog::query()
            ->where('action', AuditLog::ACTION_STOCK_DEDUCTION)
            ->where('subject_id', $pomade->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(10, $log->old_values['stock_qty']);
        $this->assertEquals(8, $log->new_values['stock_qty']);
        $this->assertEquals(2, $log->meta['qty_deducted']);
        $this->assertEquals('ticket-123', $log->meta['ticket_id']);
    }

    public function test_cannot_sell_product_if_stock_insufficient(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tidak mencukupi/i');

        \Illuminate\Support\Facades\DB::transaction(function () use ($pomade) {
            $this->stockService->decreaseStock($pomade, 5);
        });
    }

    public function test_cannot_decrease_stock_of_non_retail_product(): void
    {
        $service = $this->makeServiceProduct('Haircut');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/retail/i');

        \Illuminate\Support\Facades\DB::transaction(function () use ($service) {
            $this->stockService->decreaseStock($service, 1);
        });
    }

    public function test_decrease_stock_qty_must_be_at_least_one(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);

        $this->expectException(\InvalidArgumentException::class);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pomade) {
            $this->stockService->decreaseStock($pomade, 0);
        });
    }

    // ─── StockService::increaseStock ──────────────────────────────────────────

    public function test_increase_stock_adds_qty_correctly(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 5);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pomade) {
            $this->stockService->increaseStock($pomade, 3, 'manual adjustment');
        });

        $pomade->refresh();
        $this->assertEquals(8, $pomade->stock_qty);
    }

    public function test_increase_stock_writes_audit_log(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 5);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pomade) {
            $this->stockService->increaseStock($pomade, 4, 'restock');
        });

        $log = AuditLog::query()
            ->where('action', AuditLog::ACTION_STOCK_INCREASE)
            ->where('subject_id', $pomade->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(5, $log->old_values['stock_qty']);
        $this->assertEquals(9, $log->new_values['stock_qty']);
        $this->assertEquals('restock', $log->meta['reason']);
    }

    public function test_cannot_increase_stock_of_non_retail_product(): void
    {
        $service = $this->makeServiceProduct('Haircut');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/retail/i');

        \Illuminate\Support\Facades\DB::transaction(function () use ($service) {
            $this->stockService->increaseStock($service, 1);
        });
    }

    // ─── TicketService::completeTicket() + stock deduction ───────────────────

    public function test_stock_deducted_when_ticket_completed(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);
        $ticket = $this->makeTicketWithItem($pomade, 2);

        $this->ticketService->completeTicket($ticket);

        $pomade->refresh();
        $this->assertEquals(8, $pomade->stock_qty);
    }

    public function test_stock_deducted_once_only_on_complete(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);
        $ticket = $this->makeTicketWithItem($pomade, 1);

        $this->ticketService->completeTicket($ticket);

        // Try to complete again — should throw because canComplete() returns false
        $this->expectException(\InvalidArgumentException::class);
        $this->ticketService->completeTicket($ticket->fresh());

        // Stock should only be 9, not 8
        $pomade->refresh();
        $this->assertEquals(9, $pomade->stock_qty);
    }

    public function test_double_complete_ticket_does_not_double_deduct(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);
        $ticket = $this->makeTicketWithItem($pomade, 1);

        $this->ticketService->completeTicket($ticket);

        // Second complete attempt must fail
        try {
            $this->ticketService->completeTicket($ticket->fresh());
        } catch (\InvalidArgumentException) {
            // Expected
        }

        $pomade->refresh();
        $this->assertEquals(9, $pomade->stock_qty); // deducted exactly once
    }

    public function test_complete_ticket_fails_if_retail_stock_insufficient(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 1);
        $ticket = $this->makeTicketWithItem($pomade, 3); // qty 3 > stock 1

        $threw = false;
        try {
            $this->ticketService->completeTicket($ticket);
        } catch (\InvalidArgumentException $e) {
            $threw = true;
            $this->assertStringContainsStringIgnoringCase('tidak mencukupi', $e->getMessage());
        }

        $this->assertTrue($threw, 'Expected InvalidArgumentException for insufficient stock.');

        // Stock must remain unchanged (transaction rolled back)
        $pomade->refresh();
        $this->assertEquals(1, $pomade->stock_qty);
    }

    public function test_service_items_do_not_affect_stock(): void
    {
        $service = $this->makeServiceProduct('Haircut');
        $ticket = $this->makeTicketWithItem($service, 1);

        $this->ticketService->completeTicket($ticket);

        // Service has no stock_qty — no error, no deduction
        $service->refresh();
        $this->assertNull($service->stock_qty);
    }

    public function test_mixed_ticket_deducts_only_retail_stock(): void
    {
        $pomade = $this->makeRetailProduct('Pomade', 10);
        $haircut = $this->makeServiceProduct('Haircut');

        $ticket = $this->ticketService->createTicket([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
        ]);
        $this->ticketService->addItem($ticket, $pomade->id, 2);
        $this->ticketService->addItem($ticket, $haircut->id, 1);

        $this->ticketService->completeTicket($ticket);

        $pomade->refresh();
        $this->assertEquals(8, $pomade->stock_qty); // 10 - 2

        $haircut->refresh();
        $this->assertNull($haircut->stock_qty); // unchanged
    }
}
