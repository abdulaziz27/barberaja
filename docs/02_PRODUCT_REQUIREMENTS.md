# nyewa.id

## PRODUCT_REQUIREMENTS.md

Versi: 1.0 Status: Final Draft Target: Internal (Founder & Core Team)

------------------------------------------------------------------------

# 1. Tujuan Dokumen

Dokumen ini mendefinisikan kebutuhan produk secara detail dari sisi: -
Persona - Fitur - Alur sistem - Status & state transition - Acceptance
criteria - Edge cases

Dokumen ini menjadi acuan utama pengembangan fitur.

------------------------------------------------------------------------

# 2. Persona Detail & Kebutuhan

------------------------------------------------------------------------

## 2.1 Customer

### Tujuan:

-   Booking layanan tanpa menunggu lama
-   Mendapat pengalaman premium & jelas

### Fitur yang Dibutuhkan:

1.  Lihat daftar layanan & harga
2.  Pilih kapster (opsional)
3.  Booking tanggal & jam
4.  Antri via QR
5.  Bayar DP (jika diaktifkan)
6.  Terima notifikasi WA

### Acceptance Criteria:

-   Booking tidak boleh overlap pada staff yang sama
-   Estimasi waktu tampil jelas
-   Payment status jelas (pending/paid/failed)

------------------------------------------------------------------------

## 2.2 Owner

### Tujuan:

-   Kontrol penuh bisnis
-   Transparansi komisi
-   Transparansi escrow

### Fitur yang Dibutuhkan:

1.  Dashboard summary (omzet hari ini, minggu ini, bulan ini)
2.  Laporan gabungan multi-outlet
3.  Kelola layanan & paket
4.  Kelola komisi
5.  Lihat ledger escrow detail
6.  Request withdrawal

### Acceptance Criteria:

-   Data laporan harus konsisten dengan ledger
-   Total escrow harus sesuai mutasi
-   Withdrawal tidak boleh melebihi saldo

------------------------------------------------------------------------

## 2.3 Manager

### Tujuan:

-   Mengelola operasional outlet tertentu

### Fitur:

1.  Input booking manual
2.  Kelola POS
3.  Lihat laporan outlet tersebut

### Batasan:

-   Tidak bisa melihat outlet lain
-   Tidak bisa withdrawal global

------------------------------------------------------------------------

## 2.4 Staff

### Tujuan:

-   Melihat jadwal & komisi

### Fitur:

1.  Lihat jadwal hari ini
2.  Lihat antrian
3.  Start & complete layanan
4.  Lihat komisi pribadi

------------------------------------------------------------------------

# 3. Booking System Requirements

------------------------------------------------------------------------

## 3.1 Booking Types

-   Advance Booking
-   Walk-in via QR
-   Manual via Kasir

Semua masuk tabel yang sama.

------------------------------------------------------------------------

## 3.2 Booking Status

-   pending_payment
-   confirmed
-   in_progress
-   completed
-   cancelled
-   no_show

------------------------------------------------------------------------

## 3.3 Booking Rules

-   Tidak boleh overlap staff
-   Durasi = durasi service atau bundle
-   Cancel tidak auto-shift massal
-   Slot kosong bisa diisi walk-in

------------------------------------------------------------------------

# 4. POS Requirements

------------------------------------------------------------------------

## 4.1 Ticket Status

-   open
-   in_progress
-   completed
-   cancelled

## 4.2 Rules

-   Komisi dihitung saat completed
-   Retail stok berkurang saat completed
-   Ledger update saat payment confirmed

------------------------------------------------------------------------

# 5. Escrow & Payment Requirements

------------------------------------------------------------------------

## 5.1 Payment Flow

1.  Customer bayar
2.  Masuk Xendit balance
3.  Ledger mencatat:
    -   Platform revenue
    -   Owner escrow

------------------------------------------------------------------------

## 5.2 Withdrawal Flow

Status: - pending - success - failed - reversed

Rules: - Ledger immutable - Reversal jika gagal

------------------------------------------------------------------------

# 6. Bundle Requirements

-   Bundle hanya 1 level
-   Durasi = total child service
-   Backend expand untuk komisi
-   Tidak nested bundle

------------------------------------------------------------------------

# 7. Commission Requirements

Commission type: - percentage - fixed - none

Dihitung saat ticket completed.

------------------------------------------------------------------------

# 8. Platform Monitoring Requirements

Platform Admin dapat melihat: - Total escrow global - Total platform
revenue - Total GTV - Total withdrawal pending

Escrow global harus match saldo Xendit.

------------------------------------------------------------------------

Dokumen ini menjadi acuan pengembangan fitur tahap awal.
