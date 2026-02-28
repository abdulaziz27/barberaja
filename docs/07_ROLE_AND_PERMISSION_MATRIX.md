# barberaja.com

## ROLE_AND_PERMISSION_MATRIX.md

Versi: 1.0 Status: Final Draft Target: Backend, Security, Engineering

------------------------------------------------------------------------

# 1. Tujuan Dokumen

Dokumen ini mendefinisikan:

-   Role dalam sistem
-   Hak akses per role
-   Aksi yang diperbolehkan
-   Pembatasan data (tenant & outlet scope)
-   Mapping ke RLS (Row Level Security)

Dokumen ini mencegah privilege escalation & data leakage.

------------------------------------------------------------------------

# 2. Daftar Role

1.  platform_admin
2.  owner
3.  manager
4.  staff
5.  customer (implicit, public access only)

------------------------------------------------------------------------

# 3. Scope Akses Data

Semua data bersifat:

-   tenant-scoped
-   outlet-scoped (jika relevan)

Rule utama:

Tidak ada user yang boleh mengakses tenant lain, kecuali platform_admin.

------------------------------------------------------------------------

# 4. Permission Matrix (Ringkas)

## 4.1 Platform Admin

Akses: - Semua tenant - Semua outlet - Semua laporan global - Monitoring
escrow global - Suspend tenant

Tidak boleh: - Mengubah ledger historis

------------------------------------------------------------------------

## 4.2 Owner

Akses: - Semua outlet dalam tenant - Laporan gabungan - Laporan per
outlet - Kelola produk - Kelola paket - Kelola komisi - Lihat ledger
detail - Request withdrawal

Tidak boleh: - Mengakses tenant lain - Menghapus ledger entry

------------------------------------------------------------------------

## 4.3 Manager

Akses: - Outlet tertentu saja - Booking manual - POS - Laporan outlet
tersebut

Tidak boleh: - Withdrawal global - Lihat outlet lain - Ubah subscription

------------------------------------------------------------------------

## 4.4 Staff

Akses: - Lihat jadwal sendiri - Lihat antrian outlet - Start & complete
ticket - Lihat komisi pribadi

Tidak boleh: - Ubah harga - Lihat laporan global - Withdrawal

------------------------------------------------------------------------

## 4.5 Customer

Akses: - Halaman public outlet - Booking - Payment

Tidak memiliki akses dashboard.

------------------------------------------------------------------------

# 5. Detail Aksi per Modul

------------------------------------------------------------------------

## 5.1 Booking

Owner: - Create / Update / Cancel

Manager: - Create / Update / Cancel (outlet scope)

Staff: - Update status (in_progress, completed)

Customer: - Create booking - Cancel sebelum confirmed

------------------------------------------------------------------------

## 5.2 POS / Ticket

Owner: - Full access

Manager: - Full access outlet

Staff: - Update status ticket

------------------------------------------------------------------------

## 5.3 Ledger

Owner: - Read only

Manager: - Tidak bisa akses global ledger

Staff: - Tidak bisa akses

Platform Admin: - Read only global

------------------------------------------------------------------------

## 5.4 Withdrawal

Owner: - Create withdrawal

Manager: - Tidak bisa

Staff: - Tidak bisa

Platform Admin: - Monitoring only

------------------------------------------------------------------------

# 6. RLS Strategy Mapping

RLS Rule dasar:

user_roles.user_id = auth.uid() AND tenant_id = current tenant

Manager & Staff:

outlet_id harus sesuai dengan role binding.

Platform Admin:

Bypass RLS via service role.

------------------------------------------------------------------------

# 7. Security Invariants

1.  Tidak ada cross-tenant access.
2.  Ledger tidak dapat diubah oleh siapapun.
3.  Withdrawal hanya bisa dibuat oleh owner.
4.  Staff tidak bisa melihat data keuangan global.
5.  Semua role check dilakukan di DB dan application layer.

------------------------------------------------------------------------

Dokumen ini menjadi referensi utama implementasi akses kontrol
barberaja.com.
