# nyewa.id
## Vision & Scope Document
Versi: 1.0
Status: Final Draft
Target: Internal (Founder & Core Team)

---

# 1. Latar Belakang

Banyak barbershop premium di Indonesia masih menggunakan:
- Buku tulis untuk antrian
- WhatsApp manual untuk booking
- Excel untuk laporan
- Perhitungan komisi manual

Masalah utama:
- No-show tinggi
- Laporan tidak transparan
- Komisi staff rawan konflik
- Owner tidak punya kontrol real-time

nyewa.id hadir sebagai sistem operasional terintegrasi untuk barbershop premium.

---

# 2. Visi Produk

Menjadi sistem operasional standar untuk barbershop premium di Indonesia yang:

- Mengurangi no-show dengan sistem DP
- Mengotomatisasi komisi staff
- Memberikan transparansi laporan
- Mempermudah multi-outlet management
- Memberikan pengalaman booking premium

---

# 3. Positioning

nyewa.id adalah:

> SaaS Operasional untuk Barbershop Premium

Bukan:
- Marketplace diskon
- Platform perang harga
- Aplikasi antrian mass market

Target utama adalah barber kelas menengah ke atas (harga 35k–75k per layanan).

---

# 4. Target Market

Segmen:
- Barbershop urban
- Harga layanan 35k–75k
- 2–8 kapster
- Potensi multi-outlet

Karakteristik:
- Peduli branding
- Peduli experience
- Terbuka terhadap digital system
- Tidak ingin dibandingkan secara harga publik

---

# 5. Model Bisnis

## Revenue Stream (Tier-Based)

| Plan | Fee per Transaksi | Subscription |
|------|-------------------|--------------|
| Free | Rp 2.000 per ticket completed | Tidak ada |
| Pro | Tidak ada | Rp 99.000 per tenant per bulan (dipotong dari escrow) |
| Enterprise | Custom (dapat 0) | Custom |

Catatan:
- Subscription adalah **per tenant**, bukan per outlet.
- Fee per transaksi hanya berlaku untuk plan **free**.
- Pro plan tidak kena fee per transaksi, tapi kena subscription bulanan.
- Enterprise plan: fee dan subscription dikonfigurasi custom oleh platform admin.
- Semua pemotongan dilakukan otomatis dari saldo escrow tenant.

---

# 6. Scope Produk (Termasuk)

✔ Multi-tenant system  
✔ Multi-outlet  
✔ Booking hybrid (advance + walk-in)  
✔ QR self check-in  
✔ POS (service + retail)  
✔ Paket layanan (bundle service)  
✔ Komisi staff otomatis  
✔ Escrow DP  
✔ Withdrawal ke rekening owner  
✔ Ledger transparan  
✔ Laporan operasional  
✔ Platform admin monitoring  

---

# 7. Out of Scope (Tidak Termasuk Saat Ini)

✘ Marketplace publik dengan ranking harga  
✘ Sistem diskon massal  
✘ Inventory kompleks (multi gudang, batch, expiry)  
✘ Payroll automation lengkap  
✘ Sistem akuntansi penuh  
✘ Multi-currency  

---

# 8. Prinsip Strategis

1. SaaS-first, bukan marketplace-first.
2. Retention lebih penting daripada growth cepat.
3. Modular monolith, bukan microservice.
4. Ledger harus immutable.
5. Semua uang harus bisa direkonsiliasi.
6. Jangan over-engineer sebelum 30 outlet aktif.

---

# 9. Success Metrics Awal

Target 6–12 bulan pertama:

- 30 outlet aktif stabil
- Retention > 6 bulan
- Escrow reconciliation tanpa mismatch
- Infra cost < 15% revenue
- Support issue minimal

---

# 10. Definisi MVP

MVP dianggap berhasil jika:

- Booking berjalan stabil
- POS berjalan stabil
- Komisi otomatis akurat
- Withdrawal sukses tanpa error
- Owner bisa melihat ledger detail
- Tidak ada mismatch escrow global

---

Dokumen ini menjadi acuan arah strategis seluruh pengembangan.