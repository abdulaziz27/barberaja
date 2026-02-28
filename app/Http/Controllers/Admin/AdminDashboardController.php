<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\LedgerService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(LedgerService $ledgerService): View
    {
        $this->authorize('platformAdmin');

        $tenants = Tenant::query()->orderBy('name')->get();
        $balances = [];
        $totalEscrow = 0.0;
        foreach ($tenants as $t) {
            $bal = $ledgerService->getOwnerEscrowBalance($t->id);
            $balances[$t->id] = $bal;
            $totalEscrow += $bal;
        }

        $recentLogs = AuditLog::query()
            ->with(['tenant:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.dashboard', [
            'tenants' => $tenants,
            'balances' => $balances,
            'totalEscrow' => $totalEscrow,
            'recentLogs' => $recentLogs,
        ]);
    }
}
