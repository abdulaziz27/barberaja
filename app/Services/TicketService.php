<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\TicketItem;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        private MonetizationService $monetizationService,
        private StockService $stockService,
        private CommissionService $commissionService,
        private NotificationService $notificationService
    ) {}

    /**
     * Create a new ticket (status=open). Optional booking_id for walk-in vs from-booking.
     */
    public function createTicket(array $data): ServiceTicket
    {
        return DB::transaction(function () use ($data) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new \RuntimeException('Tenant context tidak tersedia.');
            }

            return ServiceTicket::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $data['outlet_id'],
                'booking_id' => $data['booking_id'] ?? null,
                'staff_id' => $data['staff_id'],
                'status' => ServiceTicket::STATUS_OPEN,
                'subtotal' => 0,
                'total' => 0,
                'dp_amount' => $data['dp_amount'] ?? 0,
                'payment_status' => ServiceTicket::PAYMENT_STATUS_PENDING,
            ]);
        });
    }

    /**
     * Add a product line to the ticket. Recalculates ticket subtotal/total.
     */
    public function addItem(ServiceTicket $ticket, string $productId, int $qty = 1): TicketItem
    {
        return DB::transaction(function () use ($ticket, $productId, $qty) {
            if (! $ticket->canAddItems()) {
                throw new \InvalidArgumentException('Ticket tidak dapat ditambah item (sudah selesai/dibatalkan).');
            }

            if ($qty < 1) {
                throw new \InvalidArgumentException('Qty minimal 1.');
            }

            $product = Product::query()->findOrFail($productId);
            if ($product->tenant_id !== $ticket->tenant_id) {
                throw new \InvalidArgumentException('Produk tidak berada di tenant yang sama.');
            }
            if (! $product->is_active) {
                throw new \InvalidArgumentException('Produk tidak aktif.');
            }

            $price = (float) $product->price;
            $total = $price * $qty;

            $item = $ticket->items()->create([
                'product_id' => $product->id,
                'qty' => $qty,
                'price' => $price,
                'total' => (string) $total,
            ]);

            $this->recalculateTicketTotals($ticket);

            return $item->fresh();
        });
    }

    /**
     * Set ticket status to in_progress.
     */
    public function startTicket(ServiceTicket $ticket): ServiceTicket
    {
        if (! $ticket->isOpen()) {
            throw new \InvalidArgumentException('Hanya ticket dengan status open yang dapat dimulai.');
        }

        $ticket->update(['status' => ServiceTicket::STATUS_IN_PROGRESS]);

        return $ticket->fresh();
    }

    /**
     * Complete ticket:
     * 1. Deduct retail stock for all retail items.
     * 2. Set status = completed.
     * 3. Calculate and persist staff commission via CommissionService.
     * 4. Apply platform transaction fee via MonetizationService.
     * 5. Dispatch WA notification (after transaction, non-blocking).
     *
     * All steps 1-4 are inside a single DB::transaction.
     * Stock deduction is idempotent because canComplete() returns false once status=completed.
     */
    public function completeTicket(ServiceTicket $ticket): ServiceTicket
    {
        $completed = DB::transaction(function () use ($ticket): ServiceTicket {
            if (! $ticket->canComplete()) {
                throw new \InvalidArgumentException(
                    'Ticket hanya dapat diselesaikan jika masih open/in_progress dan memiliki minimal 1 item.'
                );
            }

            $this->recalculateTicketTotals($ticket);

            // 1. Deduct retail stock
            $this->stockService->deductTicketRetailStock($ticket);

            // 2. Update status to completed (must happen before calculateCommission)
            $ticket->update(['status' => ServiceTicket::STATUS_COMPLETED]);
            $ticket->refresh();

            // 3. Calculate and persist staff commission
            $commission = $this->commissionService->calculateCommission($ticket);
            $ticket->update(['staff_commission' => $commission->amount]);

            // 4. Apply platform transaction fee
            $tenant = Tenant::query()->find($ticket->tenant_id);
            if ($tenant) {
                $this->monetizationService->applyTransactionFee($tenant, $ticket);
            }

            return $ticket->fresh();
        });

        // 5. Dispatch WA notification AFTER transaction commits — non-blocking
        $this->notificationService->notifyTicketCompleted($completed);

        return $completed;
    }

    /**
     * Cancel ticket.
     */
    public function cancelTicket(ServiceTicket $ticket): ServiceTicket
    {
        if ($ticket->isCompleted() || $ticket->isCancelled()) {
            throw new \InvalidArgumentException('Ticket yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $ticket->update(['status' => ServiceTicket::STATUS_CANCELLED]);

        return $ticket->fresh();
    }

    /**
     * Recalculate subtotal and total from items. Persist on ticket.
     */
    protected function recalculateTicketTotals(ServiceTicket $ticket): void
    {
        $sum = $ticket->items()->sum('total');
        $ticket->update([
            'subtotal' => $sum,
            'total' => $sum,
        ]);
    }
}
