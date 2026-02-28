<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\LedgerService;
use App\Services\PlanService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        private PlanService $planService,
        private LedgerService $ledgerService
    ) {}

    /**
     * Show the billing & plan management page.
     */
    public function index(): View
    {
        $tenantId = TenantContext::id();
        $tenant = Tenant::query()->findOrFail($tenantId);

        $escrowBalance = $this->ledgerService->getOwnerEscrowBalance($tenantId);
        $planComparison = $this->planService->getPlanComparison();

        // Simulate fee for 30 transactions (typical month)
        $feeSimulation = $this->planService->simulateTransactionFee($tenant->plan_type, 30);

        return view('billing.index', compact('tenant', 'escrowBalance', 'planComparison', 'feeSimulation'));
    }

    /**
     * Handle plan upgrade/downgrade.
     * Only owner can change plan.
     */
    public function upgrade(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_type' => ['required', 'string', 'in:' . implode(',', PlanService::UPGRADEABLE_PLANS)],
        ]);

        $tenantId = TenantContext::id();
        $tenant = Tenant::query()->findOrFail($tenantId);

        try {
            $this->planService->changePlan(
                $tenant,
                $request->plan_type,
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $planName = ucfirst($request->plan_type);

        return redirect()->route('billing.index')
            ->with('success', "Plan berhasil diubah ke {$planName}.");
    }
}
