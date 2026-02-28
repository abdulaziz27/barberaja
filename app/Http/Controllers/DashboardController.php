<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function index(): View
    {
        $tenantId = TenantContext::id();
        $metrics = $tenantId ? $this->dashboardService->getMetrics($tenantId) : [];

        return view('dashboard', compact('metrics'));
    }

    /**
     * Return revenue chart data as JSON (last 7 days).
     * Used by the lightweight chart on the dashboard.
     */
    public function chartData(): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['data' => []]);
        }

        $data = $this->dashboardService->getRevenueByDay($tenantId, 7);

        return response()->json(['data' => $data]);
    }
}
