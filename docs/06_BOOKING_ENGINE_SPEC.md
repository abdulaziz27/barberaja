# barberaja.com

## BOOKING_ENGINE_SPEC.md

Versi: 1.0 Status: Final Draft Target: Backend & Scheduling Logic

------------------------------------------------------------------------

# 1. Tujuan Dokumen

Dokumen ini mendefinisikan secara detail:

-   Model scheduling hybrid
-   Algoritma slot allocation
-   Walk-in handling
-   Cancelation behavior
-   State transition matrix
-   Concurrency handling
-   Edge cases

Booking engine adalah jantung operasional barberaja.com.

------------------------------------------------------------------------

# 2. Model Scheduling

Pendekatan:

Staff-Based Availability Model

Artinya:

-   Setiap booking terikat pada 1 staff
-   Tidak boleh ada overlap waktu untuk staff yang sama
-   Slot dihitung berdasarkan durasi service atau bundle

------------------------------------------------------------------------

# 3. Definisi Waktu

Setiap booking memiliki:

-   start_time
-   end_time
-   duration_minutes

Rule: end_time = start_time + duration_minutes

------------------------------------------------------------------------

# 4. Booking Types

1.  Advance Booking (tanggal & jam dipilih)
2.  Walk-in QR (masuk ke next available slot)
3.  Manual (via kasir)

Semua masuk tabel bookings.

------------------------------------------------------------------------

# 5. Slot Allocation Algorithm

Untuk Advance Booking:

1.  User pilih tanggal & jam.
2.  Sistem cek availability staff.
3.  Cek tidak ada booking dengan interval overlap.
4.  Jika valid → create booking.

Overlap rule:

(start_time \< existing_end_time) AND (end_time \> existing_start_time)

Jika true → conflict.

------------------------------------------------------------------------

# 6. Walk-in Insertion Logic

1.  Sistem hitung next available time untuk staff.
2.  Assign start_time = earliest available.
3.  end_time dihitung otomatis.

Tidak menggunakan queue shifting.

------------------------------------------------------------------------

# 7. Cancelation Behavior

------------------------------------------------------------------------

## 7.1 Cancel Sebelum Hari H

Status → cancelled

Slot menjadi kosong. Tidak auto-shift booking lain.

------------------------------------------------------------------------

## 7.2 Cancel Hari H

Status → cancelled

Slot tetap kosong. Walk-in dapat mengisi slot tersebut.

------------------------------------------------------------------------

# 8. Booking State Machine

State awal:

pending_payment → confirmed → in_progress → completed

Cabang:

pending_payment → cancelled confirmed → cancelled confirmed → no_show

Rules:

-   Tidak bisa langsung completed dari pending_payment
-   Tidak bisa kembali ke confirmed setelah completed

------------------------------------------------------------------------

# 9. No Show Policy

Jika customer tidak datang:

Status → no_show

DP tetap masuk escrow (default non-refundable).

------------------------------------------------------------------------

# 10. Concurrency Handling

Risiko: Dua user booking slot yang sama secara bersamaan.

Mitigation:

1.  Gunakan DB transaction.
2.  Gunakan constraint atau locking.
3.  Validasi ulang sebelum commit.

------------------------------------------------------------------------

# 11. Staff Schedule Override

Jika staff break:

1.  Staff bisa di-set unavailable.
2.  Booking baru tidak bisa dibuat pada interval tersebut.
3.  Booking existing tidak otomatis dipindah.

------------------------------------------------------------------------

# 12. Edge Cases

Edge Case 1: DP dibayar tapi booking dibatalkan → refund logic.

Edge Case 2: Payment webhook delay → booking tetap pending sampai paid.

Edge Case 3: Clock drift → gunakan server time sebagai source of truth.

------------------------------------------------------------------------

# 13. Invariant Rules

1.  Tidak boleh ada overlapping booking staff.
2.  Booking harus memiliki outlet_id & tenant_id.
3.  Durasi harus \> 0.
4.  Cancel tidak memindahkan booking lain.
5.  Semua perubahan status harus tercatat.

------------------------------------------------------------------------

Dokumen ini menjadi referensi utama implementasi scheduling engine
barberaja.com.
