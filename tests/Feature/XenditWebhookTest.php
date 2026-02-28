<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.xendit.dp_webhook_token', 'test-token');
    }

    protected function createBookingFixture(): Booking
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'subscription_status' => 'active',
        ]);

        TenantContext::set($tenant->id);

        $outlet = Outlet::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Outlet 1',
            'slug' => 'outlet-1',
            'address' => 'Alamat',
            'require_dp' => true,
            'allow_walkin' => true,
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'name' => 'Paket Potong',
            'type' => Product::TYPE_SERVICE,
            'price' => '20000.00',
            'duration_minutes' => 30,
            'stock_qty' => null,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => '0.00',
            'is_active' => true,
        ]);

        $staff = User::query()->create([
            'name' => 'Kapster',
            'email' => 'staff@example.test',
            'password' => 'password',
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'staff_id' => $staff->id,
            'product_id' => $product->id,
            'start_time' => now(),
            'end_time' => now()->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'source' => Booking::SOURCE_ONLINE,
            'status' => Booking::STATUS_PENDING_PAYMENT,
            'dp_amount' => 0,
            'payment_status' => 'pending_payment',
        ]);

        TenantContext::set(null);

        return $booking;
    }

    public function test_dp_webhook_creates_ledger_and_updates_booking(): void
    {
        $booking = $this->createBookingFixture();

        $payload = [
            'payment_reference' => 'pay_dp_001',
            'booking_id' => $booking->id,
            'dp_amount' => 10000,
            'fee_amount' => 2000,
        ];

        $response = $this
            ->withHeader('X-CALLBACK-TOKEN', 'test-token')
            ->postJson(route('webhooks.xendit.dp'), $payload);

        $response->assertOk()
            ->assertJson(['status' => 'ok']);

        TenantContext::set($booking->tenant_id);

        $entry = LedgerEntry::query()
            ->where('reference_id', 'pay_dp_001')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($booking->tenant_id, $entry->tenant_id);

        $lines = $entry->lines;
        $this->assertCount(3, $lines);

        $debit = (float) $lines->where('direction', 'debit')->sum('amount');
        $credit = (float) $lines->where('direction', 'credit')->sum('amount');

        $this->assertEquals($debit, $credit);
        $this->assertEquals(12000.00, $debit);

        $ownerEscrowCredit = (float) $lines
            ->where('account', LedgerService::ACCOUNT_OWNER_ESCROW)
            ->where('direction', 'credit')
            ->sum('amount');

        $platformRevenueCredit = (float) $lines
            ->where('account', LedgerService::ACCOUNT_PLATFORM_REVENUE)
            ->where('direction', 'credit')
            ->sum('amount');

        $this->assertEquals(10000.00, $ownerEscrowCredit);
        $this->assertEquals(2000.00, $platformRevenueCredit);

        $booking->refresh();
        $this->assertEquals(10000.00, (float) $booking->dp_amount);
        $this->assertSame('paid', $booking->payment_status);
    }

    public function test_dp_webhook_is_idempotent_for_same_payment_reference(): void
    {
        $booking = $this->createBookingFixture();

        $payload = [
            'payment_reference' => 'pay_dp_002',
            'booking_id' => $booking->id,
            'dp_amount' => 10000,
            'fee_amount' => 0,
        ];

        $this
            ->withHeader('X-CALLBACK-TOKEN', 'test-token')
            ->postJson(route('webhooks.xendit.dp'), $payload)
            ->assertOk();

        $this
            ->withHeader('X-CALLBACK-TOKEN', 'test-token')
            ->postJson(route('webhooks.xendit.dp'), $payload)
            ->assertOk();

        TenantContext::set($booking->tenant_id);

        $entries = LedgerEntry::query()
            ->where('reference_id', 'pay_dp_002')
            ->get();

        $this->assertCount(1, $entries);

        $booking->refresh();
        $this->assertEquals(10000.00, (float) $booking->dp_amount);
    }

    public function test_dp_webhook_rejects_invalid_signature_when_token_configured(): void
    {
        $booking = $this->createBookingFixture();

        $payload = [
            'payment_reference' => 'pay_dp_003',
            'booking_id' => $booking->id,
            'dp_amount' => 5000,
            'fee_amount' => 0,
        ];

        $response = $this
            ->withHeader('X-CALLBACK-TOKEN', 'wrong-token')
            ->postJson(route('webhooks.xendit.dp'), $payload);

        $response->assertForbidden();

        TenantContext::set($booking->tenant_id);

        $this->assertNull(
            LedgerEntry::query()
                ->where('reference_id', 'pay_dp_003')
                ->first()
        );
    }
}

