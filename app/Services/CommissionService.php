<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\StaffCommission;
use App\Models\StaffProductCommission;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Calculate and persist commission for a completed ticket.
     *
     * Priority:
     * 1. Per-staff override (staff_product_commissions) for the specific (staff, product) pair.
     * 2. Product default (product.commission_type + product.commission_value).
     *
     * Idempotent: if a StaffCommission already exists for this ticket, returns it without recalculating.
     * Must be called inside a DB::transaction.
     *
     * @throws \InvalidArgumentException if ticket is not completed
     */
    public function calculateCommission(ServiceTicket $ticket): StaffCommission
    {
        if (! $ticket->isCompleted()) {
            throw new \InvalidArgumentException('Komisi hanya dapat dihitung untuk ticket yang sudah completed.');
        }

        // Idempotency: return existing record if already calculated
        $existing = StaffCommission::query()
            ->where('ticket_id', $ticket->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $ticket->load('items.product');

        $staffId = $ticket->staff_id;
        $tenantId = $ticket->tenant_id ?? TenantContext::id();

        // Pre-load all overrides for this staff in one query
        $productIds = $ticket->items->pluck('product_id')->filter()->unique()->values()->toArray();
        $overrides = StaffProductCommission::query()
            ->where('staff_id', $staffId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $totalCommission = 0.0;
        $breakdown = [];

        foreach ($ticket->items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }

            // Resolve commission type and value (override takes priority)
            [$commissionType, $commissionValue] = $this->resolveCommission($product, $staffId, $overrides);

            $itemCommission = $this->computeItemCommission(
                (float) $item->total,
                (int) $item->qty,
                $commissionType,
                (float) $commissionValue
            );

            $totalCommission += $itemCommission;

            $breakdown[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'item_total' => (float) $item->total,
                'commission_type' => $commissionType,
                'commission_value' => (float) $commissionValue,
                'commission_amount' => round($itemCommission, 2),
                'overridden' => $overrides->has($product->id),
            ];
        }

        $totalCommission = round($totalCommission, 2);

        return StaffCommission::query()->create([
            'tenant_id' => $tenantId,
            'staff_id' => $staffId,
            'ticket_id' => $ticket->id,
            'amount' => $totalCommission,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Resolve commission type and value for a product + staff combination.
     * Returns [type, value].
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, StaffProductCommission>  $overrides  keyed by product_id
     * @return array{0: string, 1: float}
     */
    protected function resolveCommission(Product $product, string $staffId, $overrides): array
    {
        if ($overrides->has($product->id)) {
            $override = $overrides->get($product->id);
            return [$override->commission_type, (float) $override->commission_value];
        }

        return [$product->commission_type, (float) $product->commission_value];
    }

    /**
     * Compute commission amount for a single item.
     *
     * - percentage: itemTotal (price × qty) × (value / 100)
     * - fixed: value × qty (fixed per unit)
     * - none: 0
     */
    protected function computeItemCommission(float $itemTotal, int $qty, string $commissionType, float $commissionValue): float
    {
        return match ($commissionType) {
            Product::COMMISSION_PERCENTAGE => $itemTotal * ($commissionValue / 100),
            Product::COMMISSION_FIXED => $commissionValue * $qty,
            default => 0.0, // none
        };
    }

    /**
     * Set or update a per-staff commission override for a product.
     * Upserts: if override exists, updates it; otherwise creates.
     * Must be called inside a DB::transaction.
     */
    public function setStaffOverride(
        string $tenantId,
        string $staffId,
        string $productId,
        string $commissionType,
        float $commissionValue
    ): StaffProductCommission {
        if (! in_array($commissionType, [Product::COMMISSION_PERCENTAGE, Product::COMMISSION_FIXED, Product::COMMISSION_NONE], true)) {
            throw new \InvalidArgumentException("commission_type tidak valid: {$commissionType}.");
        }

        if ($commissionValue < 0) {
            throw new \InvalidArgumentException('commission_value tidak boleh negatif.');
        }

        return StaffProductCommission::query()->updateOrCreate(
            ['staff_id' => $staffId, 'product_id' => $productId],
            [
                'tenant_id' => $tenantId,
                'commission_type' => $commissionType,
                'commission_value' => $commissionValue,
            ]
        );
    }

    /**
     * Remove a per-staff commission override.
     */
    public function removeStaffOverride(string $staffId, string $productId): void
    {
        StaffProductCommission::query()
            ->where('staff_id', $staffId)
            ->where('product_id', $productId)
            ->delete();
    }
}
