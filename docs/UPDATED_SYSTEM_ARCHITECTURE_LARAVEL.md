# barberaja.com

## SYSTEM_ARCHITECTURE.md (Laravel Version)

Versi: 2.0 Stack: Laravel + Blade + Tailwind + Alpine + PostgreSQL

------------------------------------------------------------------------

# 1. Arsitektur Umum

Pendekatan: Modular Monolith berbasis Laravel.

Komponen:

Backend: - Laravel (MVC + Service Layer) - Eloquent ORM - Policy &
Middleware Authorization

Frontend: - Blade Template - TailwindCSS - Alpine.js (interaktivitas
ringan)

Database: - PostgreSQL

Queue: - Laravel Queue (Redis/Database)

Payment: - Xendit API (Webhook + Disbursement)

------------------------------------------------------------------------

# 2. Struktur Modular (Domain-Based)

app/ Domains/ Tenant/ Outlet/ Catalog/ Booking/ Ticket/ Ledger/ Payment/
Commission/ Reporting/

Controller hanya menangani request/response. Business logic berada di
Domain Service.

------------------------------------------------------------------------

# 3. Multi-Tenant Strategy

Menggunakan:

1.  tenant_id di semua tabel utama
2.  Global Scope Eloquent
3.  Middleware SetTenantContext

Contoh konsep:

Model booted(): - AddGlobalScope berdasarkan tenant_id user

Semua query otomatis terfilter tenant.

------------------------------------------------------------------------

# 4. Authorization Strategy

Gunakan:

-   Laravel Policy
-   Middleware role-based
-   user_roles table

Semua aksi sensitif diverifikasi via Policy.

------------------------------------------------------------------------

# 5. Transaction Standard

Semua flow sensitif wajib menggunakan:

DB::transaction(function() { // logic booking / ledger / withdrawal });

Tidak boleh ada operasi finansial di luar transaction.

------------------------------------------------------------------------

# 6. Scheduling Handling

Conflict dicek di:

-   Service Layer
-   DB constraint (opsional exclusion constraint)

Tidak boleh hanya mengandalkan validasi frontend.

------------------------------------------------------------------------

# 7. Webhook Handling

Route khusus webhook:

-   Validasi signature
-   Idempotent check
-   Insert ledger via transaction
-   Update status

------------------------------------------------------------------------

# 8. Observability

Gunakan:

-   Laravel logging
-   Custom log channel untuk payment
-   Monitoring escrow mismatch harian

------------------------------------------------------------------------

Dokumen ini menggantikan versi Next.js sebelumnya.
