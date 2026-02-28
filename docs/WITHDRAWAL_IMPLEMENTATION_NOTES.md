# Withdrawal Implementation Notes (Phase 7)

Withdrawal flow belum diimplementasi. Saat implementasi, ikuti ketentuan berikut.

## Idempotency

- Setiap withdrawal request **wajib** punya **unique request id** (mis. `withdrawal:{tenant_id}:{uuid}`).
- Sebelum insert ledger: cek apakah `reference_id` tersebut sudah ada di `ledger_entries`. Jika sudah → **skip** (return success atau "already processed").
- Jangan proses withdrawal yang sama dua kali (double disbursement).

## Locking & race condition

- Gunakan **`DB::transaction(...)`** untuk seluruh flow: validasi balance → insert ledger (debit owner_escrow, credit clearing) → update status withdrawal.
- Saat validasi balance, **lock** baris atau gunakan **pessimistic lock** jika perlu (mis. `Tenant::lockForUpdate()` tidak dipakai untuk balance karena balance dihitung dari ledger; yang perlu di-lock adalah **create withdrawal record** dengan unique constraint pada request_id).
- Rekomendasi: tabel `withdrawals` dengan kolom `request_id` UNIQUE. Insert withdrawal row dengan request_id di dalam transaction; jika duplicate → catch dan return idempotent response.

## Ledger (sesuai spec)

1. Debit `owner_escrow` amount  
2. Credit `clearing` amount  

Jika disbursement (Xendit) **gagal**: buat **reversal entry**:

- Credit `owner_escrow` amount  
- Debit `clearing` amount  

## Audit log

- Log `withdrawal_request` saat create withdrawal.
- Log `withdrawal_success` atau `withdrawal_failed` setelah panggilan Xendit, termasuk reversal jika gagal.

## Ringkasan flow

1. Validasi balance (dari ledger) >= amount.
2. `DB::transaction`: insert withdrawal (request_id unique), insert ledger (debit escrow, credit clearing), update status.
3. Panggil Xendit disbursement API.
4. Jika sukses: update withdrawal status; optional clear/settle clearing.
5. Jika gagal: reversal entry (dalam transaction), update status failed, log.
