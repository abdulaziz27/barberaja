# barberaja.com

## LEDGER_AND_PAYMENT_SPEC.md

Versi: 1.0 Status: Final Draft Target: Backend & Finance Logic

------------------------------------------------------------------------

# 1. Tujuan Dokumen

Dokumen ini mendefinisikan secara detail:

-   Model escrow
-   Double-entry ledger
-   Flow pembayaran
-   Flow withdrawal
-   Flow refund
-   Rekonsiliasi
-   Risk & invariant finansial

Dokumen ini adalah dokumen paling kritikal dalam sistem.

------------------------------------------------------------------------

# 2. Prinsip Dasar Keuangan

1.  Semua uang masuk ke Xendit balance platform.
2.  Platform menyimpan escrow sebagai liability.
3.  Ledger bersifat immutable (insert-only).
4.  Tidak menyimpan saldo secara langsung.
5.  Semua saldo dihitung dari ledger.
6.  Setiap transaksi harus balance (double-entry).

------------------------------------------------------------------------

# 3. Chart of Accounts

Account yang digunakan:

-   platform_revenue
-   owner_escrow
-   subscription_revenue
-   clearing (internal transitional account)

------------------------------------------------------------------------

# 4. Flow: Booking dengan DP

Contoh:

Customer bayar Rp 12.000 (10.000 DP + 2.000 Fee)

Step:

1.  Payment webhook diterima.
2.  Validasi idempotency.
3.  Insert ledger entry:

Credit platform_revenue 2.000 Credit owner_escrow 10.000

4.  Update booking status = confirmed

Invariant: Total credit harus sama dengan total uang masuk.

------------------------------------------------------------------------

# 5. Flow: Booking Tanpa DP

Tidak ada ledger entry saat booking.

Ledger hanya dibuat saat ticket completed dan pembayaran terjadi.

------------------------------------------------------------------------

# 6. Flow: Ticket Completion

Jika ada sisa pembayaran:

1.  Customer bayar full atau sisa.
2.  Webhook masuk.
3.  Insert ledger:

Credit owner_escrow sebesar total jasa - DP sebelumnya

------------------------------------------------------------------------

# 7. Flow: Withdrawal

Owner klik withdrawal.

Step:

1.  Hitung escrow balance.
2.  Jika amount \> balance → reject.
3.  Insert ledger:

Debit owner_escrow amount Credit clearing amount

4.  Call Xendit disbursement.
5.  Update withdrawal status.

Jika sukses: Clearing dianggap selesai.

Jika gagal: Insert reversal:

Credit owner_escrow amount Debit clearing amount

------------------------------------------------------------------------

# 8. Flow: Refund

Jika DP refundable:

1.  Owner approve refund.
2.  Insert ledger:

Debit owner_escrow amount Credit clearing amount

3.  Call Xendit refund API.

Jika refund gagal: Reversal entry dibuat.

------------------------------------------------------------------------

# 9. Reconciliation Strategy

Setiap hari:

1.  Hitung total owner_escrow global.
2.  Bandingkan dengan saldo Xendit balance.
3.  Selisih harus 0.

Jika tidak 0: Investigasi wajib.

------------------------------------------------------------------------

# 10. Idempotency Rules

Payment webhook:

-   Unique constraint pada payment_reference.
-   Jika reference sudah diproses → skip.

Withdrawal:

-   Unique request id.
-   Tidak boleh double process.

------------------------------------------------------------------------

# 11. Financial Invariants

WAJIB TERPENUHI:

1.  Ledger tidak boleh diupdate atau dihapus.
2.  Withdrawal tidak boleh melebihi escrow.
3.  Escrow global harus sama dengan Xendit balance.
4.  Semua transaksi harus memiliki reference_id.
5.  Tidak boleh ada orphan ledger entry.

------------------------------------------------------------------------

# 12. Risk Matrix

Risk: Double webhook\
Mitigation: Unique payment reference

Risk: Withdrawal race condition\
Mitigation: DB transaction + locking

Risk: Ledger mismatch\
Mitigation: Daily reconciliation

Risk: Negative escrow\
Mitigation: Balance validation sebelum withdrawal

------------------------------------------------------------------------

Dokumen ini menjadi standar implementasi finansial barberaja.com.
