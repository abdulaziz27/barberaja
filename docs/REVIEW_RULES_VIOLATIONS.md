# Review: Implementation vs Rules

Ringkasan pengecekan implementasi terhadap `.cursor/rules/*` dan `docs/` — pelanggaran yang ditemukan dan status.

---

## 1. Laravel Architecture (10-laravel-architecture.mdc)

| Rule | Status | Catatan |
|------|--------|--------|
| Controller hanya orchestration (tanpa business logic) | **FIXED** | Logika DP webhook dipindah ke `DpPaymentService::processWebhook()`; controller hanya panggil service + return JSON. |
| FormRequest untuk input non-trivial | OK | Webhook, booking, product, ticket pakai FormRequest. |
| Business logic di Service layer | **PARTIAL** | Booking, Ledger, Ticket, Product di service; hanya flow DP webhook yang masih di controller. |
| Flow sensitif wajib `DB::transaction` | OK | BookingService, TicketService, LedgerService, dan webhook DP semuanya pakai `DB::transaction`. |
| Jangan insert ledger dari controller | OK | Controller memanggil `LedgerService::createBalancedEntry()`, tidak insert langsung. |
| LedgerService::reverseEntry() | **NOT IMPLEMENTED** | Rule menyebut `reverseEntry()`; belum ada (direncanakan Phase 7 withdrawal/refund). |

---

## 2. Ledger & Payment (30-ledger-payments.mdc)

| Rule | Status | Catatan |
|------|--------|--------|
| Ledger immutable (no update/delete) | OK | Model events + DB trigger (SQLite/Postgres) memblokir update/delete. |
| No soft delete untuk ledger & withdrawal | OK | LedgerEntry/LedgerLine tanpa SoftDeletes. |
| Saldo dari agregasi ledger | OK | Tidak ada kolom saldo; escrow dihitung dari ledger. |
| Double-entry balanced | OK | LedgerService validasi debit = credit. |
| Setiap entry punya reference_id | OK | `ledger_entries.reference_id` wajib; unique per tenant. |
| Webhook: validasi signature + unique payment_reference → skip jika sudah diproses | OK | X-CALLBACK-TOKEN di authorize(); idempotency cek `LedgerEntry` by reference_id, early return. |
| Flow finansial wajib DB::transaction | OK | Webhook DP dan semua service pakai transaction. |
| Locking saat validasi & pemotongan escrow | OK | Webhook pakai `Booking::lockForUpdate()`. |
| Reversal entry jika disbursement/refund gagal | **N/A** | Belum ada withdrawal/refund flow (Phase 7). |

---

## 3. Multi-tenant & Scope (20-multi-tenant-and-scope.mdc)

| Rule | Status | Catatan |
|------|--------|--------|
| Tabel utama punya tenant_id (UUID) + index | OK | tenants, outlets, users, user_roles, products, bookings, service_tickets, ledger_entries punya tenant_id. |
| Data spesifik outlet punya outlet_id | OK | products, bookings, service_tickets punya outlet_id. |
| Tenant scoping (middleware + global scope) | OK | SetTenantContext + BelongsToTenant/TenantScope. |
| Tidak boleh query tanpa tenant filter untuk data tenant-scoped | OK | Model utama pakai TenantScope; webhook set TenantContext dari booking. |
| Tabel snake_case, model singular, UUID PK | OK | Konsisten. |
| Soft delete hanya non-finansial | OK | Hanya Product (catalog); ledger tanpa soft delete. |

---

## 4. Booking Engine (40-booking-engine.mdc)

| Rule | Status | Catatan |
|------|--------|--------|
| Overlap rule: (start_time < existing_end_time) AND (end_time > existing_start_time) | OK | BookingService::validateAvailability() mengimplementasikan persis. |
| end_time = start_time + duration_minutes | OK | Di BookingService create. |
| DB::transaction + re-check untuk concurrency | OK | createBooking dalam transaction; validateAvailability dipanggil di dalam. |
| Semua perubahan status booking tercatat (audit trail) | **VIOLATION** | Rule: "Semua perubahan status harus tercatat (audit trail)". Tidak ada tabel audit/history untuk status booking (mis. booking_status_history). |

---

## 5. BarberAja Core (00-barberaja-core.mdc)

| Rule | Status | Catatan |
|------|--------|--------|
| No cross-tenant access (kecuali platform_admin) | OK | Policy + tenant scope; belum ada fitur platform_admin cross-tenant. |
| Ledger immutable + double-entry balanced | OK | Sudah. |
| Tidak menyimpan saldo; idempotent webhook/withdrawal | OK | Saldo dari ledger; webhook idempotent. |

---

## 6. Lain-lain (docs + runtime)

| Item | Status | Catatan |
|------|--------|--------|
| CSRF pada route webhook | **FIXED** | Di `bootstrap/app.php` ditambah `validateCsrfTokens(except: ['webhooks/xendit/*'])`. |
| Chart of accounts | OK | platform_revenue, owner_escrow, subscription_revenue, clearing dipakai di LedgerService. |

---

## Ringkasan pelanggaran

1. ~~**Controller tebal (business logic di webhook)**~~ **FIXED** — `DpPaymentService::processWebhook()` dipakai; controller hanya orchestration.
2. **Booking status tanpa audit trail** — Tambah mekanisme pencatatan perubahan status (tabel history atau event log) sesuai 40-booking-engine.
3. ~~**CSRF memblokir webhook**~~ **FIXED** — `bootstrap/app.php`: `validateCsrfTokens(except: ['webhooks/xendit/*'])`.
4. **LedgerService::reverseEntry()** — Belum ada; rencanakan di Phase 7 (withdrawal/refund reversal).

---

## Rekomendasi perbaikan (sisa)

1. **Audit trail booking** — Rancang tabel/event untuk riwayat status booking; panggil dari BookingService (dan dari webhook/flow lain yang ubah status).
2. **reverseEntry()** — Implementasi saat Phase 7 withdrawal/refund.
