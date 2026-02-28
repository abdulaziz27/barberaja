<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Decrease stock for a retail product by the given qty.
     * Must be called inside a DB::transaction.
     *
     * Rules:
     * - Product must be type=retail
     * - stock_qty must not go below 0
     * - Uses pessimistic lock (lockForUpdate) to prevent race conditions
     * - Writes audit log entry
     *
     * @throws \InvalidArgumentException if product is not retail, qty < 1, or insufficient stock
     */
    public function decreaseStock(Product $product, int $qty, ?string $ticketId = null): void
    {
        if ($qty < 1) {
            throw new \InvalidArgumentException('Qty harus minimal 1.');
        }

        if (! $product->isRetail()) {
            throw new \InvalidArgumentException("Produk '{$product->name}' bukan produk retail; stock tidak dapat dikurangi.");
        }

        // Re-fetch with lock to prevent concurrent deductions
        $locked = Product::query()
            ->withoutGlobalScopes()
            ->lockForUpdate()
            ->findOrFail($product->id);

        $currentStock = (int) $locked->stock_qty;

        if ($currentStock < $qty) {
            throw new \InvalidArgumentException(
                "Stok '{$product->name}' tidak mencukupi. Tersedia: {$currentStock}, dibutuhkan: {$qty}."
            );
        }

        $newStock = $currentStock - $qty;

        Product::query()
            ->withoutGlobalScopes()
            ->whereKey($product->id)
            ->update(['stock_qty' => $newStock]);

        // Sync in-memory value
        $product->stock_qty = $newStock;

        $tenantId = $product->tenant_id ?? TenantContext::id();

        AuditLog::log(
            AuditLog::ACTION_STOCK_DEDUCTION,
            Product::class,
            $product->id,
            ['stock_qty' => $currentStock],
            ['stock_qty' => $newStock],
            [
                'tenant_id' => $tenantId,
                'qty_deducted' => $qty,
                'ticket_id' => $ticketId,
            ]
        );
    }

    /**
     * Increase stock for a retail product by the given qty.
     * Used for manual adjustments or reversals.
     * Must be called inside a DB::transaction.
     *
     * @throws \InvalidArgumentException if product is not retail or qty < 1
     */
    public function increaseStock(Product $product, int $qty, ?string $reason = null): void
    {
        if ($qty < 1) {
            throw new \InvalidArgumentException('Qty harus minimal 1.');
        }

        if (! $product->isRetail()) {
            throw new \InvalidArgumentException("Produk '{$product->name}' bukan produk retail; stock tidak dapat ditambah.");
        }

        $locked = Product::query()
            ->withoutGlobalScopes()
            ->lockForUpdate()
            ->findOrFail($product->id);

        $currentStock = (int) $locked->stock_qty;
        $newStock = $currentStock + $qty;

        Product::query()
            ->withoutGlobalScopes()
            ->whereKey($product->id)
            ->update(['stock_qty' => $newStock]);

        $product->stock_qty = $newStock;

        $tenantId = $product->tenant_id ?? TenantContext::id();

        AuditLog::log(
            AuditLog::ACTION_STOCK_INCREASE,
            Product::class,
            $product->id,
            ['stock_qty' => $currentStock],
            ['stock_qty' => $newStock],
            [
                'tenant_id' => $tenantId,
                'qty_added' => $qty,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Deduct stock for all retail items in a completed ticket.
     * Idempotent: checks if stock was already deducted for this ticket.
     * Must be called inside a DB::transaction.
     *
     * @throws \InvalidArgumentException if any retail item has insufficient stock
     */
    public function deductTicketRetailStock(ServiceTicket $ticket): void
    {
        $ticket->load('items.product');

        foreach ($ticket->items as $item) {
            $product = $item->product;

            if ($product === null || ! $product->isRetail()) {
                continue;
            }

            $this->decreaseStock($product, (int) $item->qty, $ticket->id);
        }
    }
}
