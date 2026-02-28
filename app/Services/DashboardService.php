<?php

namespace App\Services;

use App\Models\LedgerLine;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\StaffCommission;
use App\Models\Tenant;
use App\Models\TicketItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Get all dashboard metrics for a tenant.
     * All queries are optimized (no N+1).
     */
    public function getMetrics(string $tenantId): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        return [
            'revenue_today' => $this->getRevenue($tenantId, $today, $today->copy()->endOfDay()),
            'revenue_month' => $this->getRevenue($tenantId, $monthStart, $monthEnd),
            'tickets_today' => $this->getTicketCount($tenantId, $today, $today->copy()->endOfDay()),
            'tickets_month' => $this->getTicketCount($tenantId, $monthStart, $monthEnd),
            'escrow_balance' => $this->ledgerService->getOwnerEscrowBalance($tenantId),
            'top_services' => $this->getTopServices($tenantId, $monthStart, $monthEnd, 3),
            'top_staff' => $this->getTopStaff($tenantId, $monthStart, $monthEnd, 3),
            'retail_sales_month' => $this->getRetailSales($tenantId, $monthStart, $monthEnd),
            'plan_info' => $this->getPlanInfo($tenantId),
            'revenue_last_7_days' => $this->getRevenueByDay($tenantId, 7),
        ];
    }

    /**
     * Revenue from completed tickets (sum of ticket totals) in a date range.
     * Uses ticket total, not ledger (ledger is for escrow/platform fee).
     */
    public function getRevenue(string $tenantId, Carbon $from, Carbon $to): float
    {
        return (float) ServiceTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ServiceTicket::STATUS_COMPLETED)
            ->whereBetween('updated_at', [$from, $to])
            ->sum('total');
    }

    /**
     * Count completed tickets in a date range.
     */
    public function getTicketCount(string $tenantId, Carbon $from, Carbon $to): int
    {
        return ServiceTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ServiceTicket::STATUS_COMPLETED)
            ->whereBetween('updated_at', [$from, $to])
            ->count();
    }

    /**
     * Top N services by revenue (sum of ticket_items.total) in a date range.
     * Single query with join — no N+1.
     *
     * @return array<int, array{product_id: string, name: string, total_revenue: float, count: int}>
     */
    public function getTopServices(string $tenantId, Carbon $from, Carbon $to, int $limit = 3): array
    {
        return TicketItem::query()
            ->select([
                'ticket_items.product_id',
                DB::raw('MAX(products.name) as name'),
                DB::raw('SUM(ticket_items.total) as total_revenue'),
                DB::raw('SUM(ticket_items.qty) as count'),
            ])
            ->join('products', 'ticket_items.product_id', '=', 'products.id')
            ->join('service_tickets', 'ticket_items.ticket_id', '=', 'service_tickets.id')
            ->where('service_tickets.tenant_id', $tenantId)
            ->where('service_tickets.status', ServiceTicket::STATUS_COMPLETED)
            ->whereIn('products.type', [Product::TYPE_SERVICE, Product::TYPE_BUNDLE])
            ->whereBetween('service_tickets.updated_at', [$from, $to])
            ->groupBy('ticket_items.product_id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'total_revenue' => (float) $row->total_revenue,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Top N staff by commission earned in a date range.
     * Single query with join — no N+1.
     *
     * @return array<int, array{staff_id: string, name: string, total_commission: float, ticket_count: int}>
     */
    public function getTopStaff(string $tenantId, Carbon $from, Carbon $to, int $limit = 3): array
    {
        return StaffCommission::query()
            ->select([
                'staff_commissions.staff_id',
                DB::raw('MAX(users.name) as name'),
                DB::raw('SUM(staff_commissions.amount) as total_commission'),
                DB::raw('COUNT(staff_commissions.id) as ticket_count'),
            ])
            ->join('users', 'staff_commissions.staff_id', '=', 'users.id')
            ->where('staff_commissions.tenant_id', $tenantId)
            ->whereBetween('staff_commissions.created_at', [$from, $to])
            ->groupBy('staff_commissions.staff_id')
            ->orderByDesc('total_commission')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'staff_id' => $row->staff_id,
                'name' => $row->name,
                'total_commission' => (float) $row->total_commission,
                'ticket_count' => (int) $row->ticket_count,
            ])
            ->toArray();
    }

    /**
     * Retail sales summary (total revenue from retail items) in a date range.
     */
    public function getRetailSales(string $tenantId, Carbon $from, Carbon $to): float
    {
        return (float) TicketItem::query()
            ->join('products', 'ticket_items.product_id', '=', 'products.id')
            ->join('service_tickets', 'ticket_items.ticket_id', '=', 'service_tickets.id')
            ->where('service_tickets.tenant_id', $tenantId)
            ->where('service_tickets.status', ServiceTicket::STATUS_COMPLETED)
            ->where('products.type', Product::TYPE_RETAIL)
            ->whereBetween('service_tickets.updated_at', [$from, $to])
            ->sum('ticket_items.total');
    }

    /**
     * Plan info for the tenant.
     */
    public function getPlanInfo(string $tenantId): array
    {
        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return [];
        }

        return [
            'plan_type' => $tenant->plan_type,
            'plan_status' => $tenant->plan_status,
            'is_overdue' => $tenant->isSubscriptionOverdue(),
            'plan_expires_at' => $tenant->plan_expires_at?->format('d M Y'),
        ];
    }

    /**
     * Revenue per day for the last N days (for chart).
     * Returns array of ['date' => 'YYYY-MM-DD', 'revenue' => float].
     *
     * @return array<int, array{date: string, revenue: float}>
     */
    public function getRevenueByDay(string $tenantId, int $days = 7): array
    {
        $from = Carbon::today()->subDays($days - 1);
        $to = Carbon::today()->endOfDay();

        // Single query: group by date
        $rows = ServiceTicket::query()
            ->select([
                DB::raw('DATE(updated_at) as date'),
                DB::raw('SUM(total) as revenue'),
            ])
            ->where('tenant_id', $tenantId)
            ->where('status', ServiceTicket::STATUS_COMPLETED)
            ->whereBetween('updated_at', [$from, $to])
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in missing days with 0
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'revenue' => isset($rows[$date]) ? (float) $rows[$date]->revenue : 0.0,
            ];
        }

        return $result;
    }
}
