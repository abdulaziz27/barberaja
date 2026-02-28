<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Services\LedgerService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'subscription_status' => 'active',
        ]);

        TenantContext::set($tenant->id);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    public function test_create_balanced_entry_succeeds_and_is_balanced(): void
    {
        $service = app(LedgerService::class);

        $entry = $service->createBalancedEntry('pay_123', [
            ['account' => LedgerService::ACCOUNT_CLEARING, 'direction' => 'debit', 'amount' => '10000.00'],
            ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => 'credit', 'amount' => '10000.00'],
        ], 'Test entry');

        $this->assertInstanceOf(LedgerEntry::class, $entry);
        $this->assertSame('pay_123', $entry->reference_id);
        $this->assertCount(2, $entry->lines);

        $debit = (float) $entry->lines->where('direction', 'debit')->sum('amount');
        $credit = (float) $entry->lines->where('direction', 'credit')->sum('amount');

        $this->assertEquals($debit, $credit);
        $this->assertEquals(10000.00, $debit);
    }

    public function test_unbalanced_entry_is_rejected(): void
    {
        $service = app(LedgerService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->createBalancedEntry('pay_124', [
            ['account' => LedgerService::ACCOUNT_CLEARING, 'direction' => 'debit', 'amount' => '10000.00'],
            ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => 'credit', 'amount' => '9999.99'],
        ]);
    }

    public function test_immutable_prevents_eloquent_update_and_delete(): void
    {
        $service = app(LedgerService::class);
        $entry = $service->createEscrowCredit('pay_125', '12000.00');

        try {
            $entry->update(['description' => 'Nope']);
            $this->fail('Expected immutable ledger to block update.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        try {
            $entry->delete();
            $this->fail('Expected immutable ledger to block delete.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
    }

    public function test_immutable_prevents_query_builder_update_via_db_trigger(): void
    {
        $service = app(LedgerService::class);
        $entry = $service->createEscrowCredit('pay_126', '12000.00');

        $this->expectException(QueryException::class);
        LedgerEntry::query()->whereKey($entry->id)->update(['description' => 'Nope']);
    }
}

