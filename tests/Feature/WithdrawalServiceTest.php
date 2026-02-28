<?php

namespace Tests\Feature;

use App\Contracts\XenditDisbursementClient;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Models\Tenant;
use App\Models\Withdrawal;
use App\Services\LedgerService;
use App\Services\WithdrawalService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
        ]);
    }

    protected function seedEscrow(float $amount): void
    {
        TenantContext::set($this->tenant->id);
        app(LedgerService::class)->createBalancedEntry(
            'seed_'.uniqid(),
            [
                ['account' => LedgerService::ACCOUNT_CLEARING, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => $amount],
                ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => $amount],
            ],
            'Seed'
        );
        TenantContext::set(null);
    }

    /** @return XenditDisbursementClient */
    protected function mockXenditSuccess(): XenditDisbursementClient
    {
        return new class implements XenditDisbursementClient {
            public function createDisbursement(float $amount, string $bankCode, string $accountNumber, string $externalId, string $description): array
            {
                return ['external_id' => $externalId, 'status' => 'COMPLETED'];
            }
        };
    }

    /** @return XenditDisbursementClient */
    protected function mockXenditFailure(): XenditDisbursementClient
    {
        return new class implements XenditDisbursementClient {
            public function createDisbursement(float $amount, string $bankCode, string $accountNumber, string $externalId, string $description): array
            {
                throw new \RuntimeException('Xendit disbursement failed');
            }
        };
    }

    /** @return XenditDisbursementClient */
    protected function mockXenditPending(): XenditDisbursementClient
    {
        return new class implements XenditDisbursementClient {
            public function createDisbursement(float $amount, string $bankCode, string $accountNumber, string $externalId, string $description): array
            {
                return ['external_id' => $externalId, 'status' => 'PENDING'];
            }
        };
    }

    public function test_idempotency_same_request_id_returns_existing_no_double_ledger(): void
    {
        $this->seedEscrow(100000);
        $requestId = 'wd-'.$this->tenant->id.'-001';
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditSuccess());

        $service = app(WithdrawalService::class);
        $first = $service->requestWithdrawal($requestId, $this->tenant->id, 50000, 'BCA', '1234567890');
        $second = $service->requestWithdrawal($requestId, $this->tenant->id, 50000, 'BCA', '1234567890');

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, Withdrawal::query()->where('request_id', $requestId)->get());
        TenantContext::set($this->tenant->id);
        $entries = LedgerEntry::query()->where('reference_id', $requestId)->get();
        $this->assertCount(1, $entries);
    }

    public function test_double_request_same_request_id_second_returns_same_withdrawal(): void
    {
        $this->seedEscrow(100000);
        $requestId = 'wd-double-'.uniqid();
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditSuccess());

        $service = app(WithdrawalService::class);
        $w1 = $service->requestWithdrawal($requestId, $this->tenant->id, 20000, 'BCA', '111');
        $w2 = $service->requestWithdrawal($requestId, $this->tenant->id, 20000, 'BCA', '111');

        $this->assertEquals($w1->id, $w2->id);
        $this->assertEquals(1, LedgerEntry::query()->where('reference_id', $requestId)->count());
    }

    public function test_insufficient_balance_throws_no_withdrawal_no_ledger(): void
    {
        $this->seedEscrow(10000);
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditSuccess());

        $service = app(WithdrawalService::class);
        $requestId = 'wd-insufficient-'.uniqid();

        $this->expectException(\App\Services\InsufficientBalanceException::class);
        $service->requestWithdrawal($requestId, $this->tenant->id, 50000, 'BCA', '111');
    }

    public function test_insufficient_balance_does_not_create_withdrawal_or_ledger(): void
    {
        $this->seedEscrow(10000);
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditSuccess());
        $requestId = 'wd-insufficient-'.uniqid();

        try {
            app(WithdrawalService::class)->requestWithdrawal($requestId, $this->tenant->id, 50000, 'BCA', '111');
        } catch (\App\Services\InsufficientBalanceException $e) {
            //
        }

        $this->assertCount(0, Withdrawal::query()->where('request_id', $requestId)->get());
        TenantContext::set($this->tenant->id);
        $this->assertCount(0, LedgerEntry::query()->where('reference_id', $requestId)->get());
    }

    public function test_failed_disbursement_creates_reversal_and_marks_failed(): void
    {
        $this->seedEscrow(100000);
        $requestId = 'wd-fail-'.uniqid();
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditFailure());

        $service = app(WithdrawalService::class);
        $withdrawal = $service->requestWithdrawal($requestId, $this->tenant->id, 30000, 'BCA', '222');

        $withdrawal->refresh();
        $this->assertTrue($withdrawal->isFailed());
        $this->assertNotNull($withdrawal->failure_reason);

        TenantContext::set($this->tenant->id);
        $reversalRef = 'reversal:'.$requestId;
        $reversal = LedgerEntry::query()->where('reference_id', $reversalRef)->first();
        $this->assertNotNull($reversal);
        $creditEscrow = (float) $reversal->lines->where('account', LedgerService::ACCOUNT_OWNER_ESCROW)->where('direction', 'credit')->sum('amount');
        $this->assertEquals(30000, $creditEscrow);
    }

    public function test_failed_disbursement_audit_log(): void
    {
        $this->seedEscrow(100000);
        $requestId = 'wd-audit-fail-'.uniqid();
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditFailure());

        app(WithdrawalService::class)->requestWithdrawal($requestId, $this->tenant->id, 25000, 'BCA', '333');

        $withdrawalId = Withdrawal::query()->where('request_id', $requestId)->value('id');
        $failedLog = AuditLog::query()->where('action', AuditLog::ACTION_WITHDRAWAL_FAILED)->where('subject_id', $withdrawalId)->first();
        $this->assertNotNull($failedLog);
    }

    public function test_disbursement_webhook_failed_creates_reversal(): void
    {
        $this->seedEscrow(80000);
        $requestId = 'wd-webhook-fail-'.uniqid();
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditPending());
        app(WithdrawalService::class)->requestWithdrawal($requestId, $this->tenant->id, 15000, 'BCA', '444');
        $withdrawal = Withdrawal::query()->where('request_id', $requestId)->first();
        $this->assertTrue($withdrawal->isPending());

        app(WithdrawalService::class)->handleDisbursementWebhook($requestId, 'FAILED');
        $withdrawal->refresh();
        $this->assertTrue($withdrawal->isFailed());
        TenantContext::set($this->tenant->id);
        $this->assertNotNull(LedgerEntry::query()->where('reference_id', 'reversal:'.$requestId)->first());
    }

    public function test_disbursement_webhook_idempotent_when_already_processed(): void
    {
        $this->seedEscrow(80000);
        $requestId = 'wd-webhook-idem-'.uniqid();
        $this->app->instance(XenditDisbursementClient::class, $this->mockXenditFailure());
        app(WithdrawalService::class)->requestWithdrawal($requestId, $this->tenant->id, 10000, 'BCA', '555');
        TenantContext::set($this->tenant->id);
        $reversalRef = 'reversal:'.$requestId;
        $countBefore = LedgerEntry::query()->where('reference_id', $reversalRef)->count();
        $this->assertEquals(1, $countBefore);

        app(WithdrawalService::class)->handleDisbursementWebhook($requestId, 'FAILED');
        app(WithdrawalService::class)->handleDisbursementWebhook($requestId, 'FAILED');
        $countAfter = LedgerEntry::query()->where('reference_id', $reversalRef)->count();
        $this->assertEquals(1, $countAfter);
    }
}
