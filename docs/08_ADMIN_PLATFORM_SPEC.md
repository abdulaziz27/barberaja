# barberaja.com

## ADMIN_PLATFORM_SPEC.md

Versi: 1.0 Status: Final Draft Target: Founder / Platform Owner

------------------------------------------------------------------------

# 1. Tujuan Dokumen

Dokumen ini mendefinisikan:

-   Monitoring global SaaS
-   Kontrol escrow liability
-   Kontrol revenue platform
-   Tenant health monitoring
-   Suspend & risk handling policy

Dokumen ini khusus untuk pemilik platform barberaja.com.

------------------------------------------------------------------------

# 2. Prinsip Utama Platform Control

1.  Escrow adalah liability.
2.  Escrow global wajib match saldo Xendit.
3.  Ledger tidak boleh diubah.
4.  Semua tenant activity harus bisa dimonitor.
5.  Platform revenue harus terpisah jelas dari escrow.

------------------------------------------------------------------------

# 3. Global Financial Dashboard

Platform Admin harus bisa melihat:

-   Total Tenant Aktif
-   Total Outlet Aktif
-   Total Booking (hari ini / bulan ini)
-   Total GTV (Gross Transaction Value)
-   Total Platform Revenue
-   Total Escrow Liability
-   Total Withdrawal Pending
-   Total Withdrawal Failed

------------------------------------------------------------------------

# 4. Escrow Monitoring

Escrow Liability Global dihitung dari:

SUM(credit owner_escrow) - SUM(debit owner_escrow)

Setiap hari harus dilakukan:

1.  Ambil saldo Xendit balance.
2.  Bandingkan dengan escrow global.
3.  Selisih harus 0.

Jika tidak 0: - Tandai sebagai CRITICAL - Investigasi wajib

------------------------------------------------------------------------

# 5. Tenant Health Monitoring

Setiap tenant memiliki status:

-   active
-   suspended
-   cancelled

Platform dapat melihat:

-   Booking per bulan
-   GTV per bulan
-   Withdrawal frequency
-   Subscription status
-   Error rate webhook

Tenant dapat di-suspend jika:

-   Subscription unpaid
-   Fraud terdeteksi
-   Abuse sistem

------------------------------------------------------------------------

# 6. Revenue Monitoring

Platform Revenue dihitung dari:

SUM(platform_revenue account)

Harus dipisahkan dari:

-   owner_escrow
-   subscription_revenue

Dashboard harus bisa menunjukkan:

-   Revenue harian
-   Revenue bulanan
-   Revenue per outlet
-   Revenue per tenant

------------------------------------------------------------------------

# 7. Withdrawal Risk Control

Platform Admin dapat melihat:

-   Withdrawal pending \> X jam
-   Withdrawal gagal
-   Withdrawal jumlah besar (threshold alert)

Jika withdrawal gagal: - Pastikan reversal entry dibuat - Pastikan saldo
escrow kembali normal

------------------------------------------------------------------------

# 8. Fraud & Abuse Detection

Indikator risiko:

-   Booking spike abnormal
-   Refund spike abnormal
-   Withdrawal cepat setelah DP besar
-   Multiple failed payments

Mitigasi:

-   Manual review
-   Temporary suspend
-   Limit withdrawal threshold

------------------------------------------------------------------------

# 9. Audit Log

Semua aksi sensitif harus dicatat:

-   Withdrawal request
-   Refund
-   Subscription deduction
-   Role change
-   Suspend tenant

Audit log tidak boleh dihapus.

------------------------------------------------------------------------

# 10. Disaster Recovery & Backup

-   Daily database backup
-   Backup ledger table prioritas tinggi
-   Test restore berkala

Escrow data adalah prioritas utama.

------------------------------------------------------------------------

# 11. Critical Invariants

WAJIB BENAR:

1.  Escrow global = Xendit balance.
2.  Ledger immutable.
3.  Withdrawal tidak menyebabkan saldo negatif.
4.  Tidak ada cross-tenant access.
5.  Tidak ada orphan ledger entry.

------------------------------------------------------------------------

Dokumen ini memastikan barberaja.com berjalan aman, stabil, dan
terkontrol sebagai SaaS finansial ringan.
