<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PlanService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Barbershop Billing',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $outlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Billing',
            'slug' => 'outlet-billing-test',
        ]);

        $this->owner = User::query()->create([
            'name' => 'Owner Billing',
            'email' => 'owner-billing@test.test',
            'password' => bcrypt('password'),
        ]);

        UserRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        TenantContext::set($this->tenant->id);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    // ─── PlanService::changePlan ──────────────────────────────────────────────

    public function test_change_plan_from_free_to_pro_succeeds(): void
    {
        $planService = app(PlanService::class);

        $updated = $planService->changePlan($this->tenant, Tenant::PLAN_TYPE_PRO, $this->owner->id);

        $this->assertSame(Tenant::PLAN_TYPE_PRO, $updated->plan_type);
        $this->assertSame(Tenant::PLAN_STATUS_ACTIVE, $updated->plan_status);
        $this->assertNotNull($updated->plan_expires_at);
        $this->assertNull($updated->subscription_overdue_at);
    }

    public function test_change_plan_writes_audit_log(): void
    {
        $planService = app(PlanService::class);

        $planService->changePlan($this->tenant, Tenant::PLAN_TYPE_PRO, $this->owner->id);

        $log = AuditLog::query()
            ->where('action', AuditLog::ACTION_PLAN_CHANGE)
            ->where('subject_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(Tenant::PLAN_TYPE_FREE, $log->old_values['plan_type']);
        $this->assertEquals(Tenant::PLAN_TYPE_PRO, $log->new_values['plan_type']);
    }

    public function test_change_plan_to_same_plan_throws(): void
    {
        $planService = app(PlanService::class);

        $this->expectException(\InvalidArgumentException::class);
        $planService->changePlan($this->tenant, Tenant::PLAN_TYPE_FREE, $this->owner->id);
    }

    public function test_change_plan_to_invalid_plan_throws(): void
    {
        $planService = app(PlanService::class);

        $this->expectException(\InvalidArgumentException::class);
        $planService->changePlan($this->tenant, 'enterprise', $this->owner->id);
    }

    public function test_downgrade_to_free_clears_expiry(): void
    {
        // First upgrade to pro
        $planService = app(PlanService::class);
        $planService->changePlan($this->tenant, Tenant::PLAN_TYPE_PRO, $this->owner->id);

        // Then downgrade to free
        $updated = $planService->changePlan($this->tenant->fresh(), Tenant::PLAN_TYPE_FREE, $this->owner->id);

        $this->assertSame(Tenant::PLAN_TYPE_FREE, $updated->plan_type);
        $this->assertNull($updated->plan_expires_at);
    }

    public function test_upgrade_clears_overdue_flag(): void
    {
        // Mark as overdue
        $this->tenant->update(['subscription_overdue_at' => now()]);

        $planService = app(PlanService::class);
        $updated = $planService->changePlan($this->tenant->fresh(), Tenant::PLAN_TYPE_PRO, $this->owner->id);

        $this->assertNull($updated->subscription_overdue_at);
    }

    // ─── HTTP routes ──────────────────────────────────────────────────────────

    public function test_billing_page_loads_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->owner)->get(route('billing.index'));

        $response->assertStatus(200);
        $response->assertSee('Billing & Plan');
        $response->assertSee('Free');
        $response->assertSee('Pro');
    }

    public function test_upgrade_via_http_changes_plan(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('billing.upgrade'), ['plan_type' => Tenant::PLAN_TYPE_PRO]);

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('success');

        $this->tenant->refresh();
        $this->assertSame(Tenant::PLAN_TYPE_PRO, $this->tenant->plan_type);
    }

    public function test_upgrade_with_invalid_plan_returns_error(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('billing.upgrade'), ['plan_type' => 'invalid_plan']);

        $response->assertSessionHasErrors(['plan_type']);
    }

    // ─── Fee simulation ───────────────────────────────────────────────────────

    public function test_fee_simulation_correct_for_free_plan(): void
    {
        $planService = app(PlanService::class);

        $fee = $planService->simulateTransactionFee(Tenant::PLAN_TYPE_FREE, 30);

        $this->assertEquals(30 * Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE, $fee);
    }

    public function test_fee_simulation_zero_for_pro_plan(): void
    {
        $planService = app(PlanService::class);

        $fee = $planService->simulateTransactionFee(Tenant::PLAN_TYPE_PRO, 30);

        $this->assertEquals(0.0, $fee);
    }
}
