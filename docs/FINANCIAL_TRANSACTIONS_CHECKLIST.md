# Financial Actions — DB::transaction Checklist

Semua aksi finansial wajib dibungkus `DB::transaction(...)`.

| Lokasi | Aksi | Wrapped |
|--------|------|---------|
| BookingService::createBooking | Create booking | ✅ DB::transaction |
| BookingService::cancelBooking | Update booking status | ✅ DB::transaction |
| TicketService::createTicket | Create ticket | ✅ DB::transaction |
| TicketService::addItem | Add item + recalc totals | ✅ DB::transaction |
| TicketService::completeTicket | Commission + status + MonetizationService::applyTransactionFee | ✅ DB::transaction (fee dipanggil di dalam) |
| LedgerService::createBalancedEntry | Insert entry + lines | ✅ DB::transaction |
| DpPaymentService::processWebhook | Idempotency check + ledger + update booking | ✅ DB::transaction |
| MonetizationService::deductSubscription | Balance check + ledger + overdue flag + audit log | ✅ DB::transaction |

**Withdrawal (belum ada):** Saat implementasi, seluruh flow (validasi balance, insert withdrawal, insert ledger, update status) wajib dalam satu `DB::transaction`. Lihat `docs/WITHDRAWAL_IMPLEMENTATION_NOTES.md`.
