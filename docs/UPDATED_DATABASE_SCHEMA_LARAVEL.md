# barberaja.com

## DATABASE_SCHEMA.md (Laravel Ready)

Versi: 2.0

------------------------------------------------------------------------

# 1. Konvensi Laravel

-   Semua tabel snake_case
-   Model singular
-   UUID sebagai primary key
-   created_at & updated_at wajib
-   SoftDeletes untuk tabel non-finansial

Ledger & withdrawal TIDAK boleh soft delete.

------------------------------------------------------------------------

# 2. Migration Strategy

Gunakan Laravel migration:

-   foreignId dengan constrained()
-   index tenant_id
-   composite index untuk booking

Contoh:

\$table-\>uuid('tenant_id')-\>index();
\$table-\>uuid('outlet_id')-\>index();

------------------------------------------------------------------------

# 3. Booking Overlap Constraint

Disarankan:

-   Check overlap di Service Layer
-   Tambahkan index staff_id + start_time
-   Optional: PostgreSQL exclusion constraint

------------------------------------------------------------------------

# 4. Ledger Table Rules

-   Tidak boleh update
-   Tidak boleh delete
-   Gunakan model tanpa fillable update
-   Disable mass update

------------------------------------------------------------------------

# 5. Soft Delete Policy

Gunakan soft delete untuk:

-   products
-   outlets
-   staff

Jangan gunakan soft delete untuk:

-   ledger_entries
-   withdrawals

------------------------------------------------------------------------

# 6. Derived Balance (Service Layer Only)

Escrow balance dihitung via query aggregate. Tidak boleh disimpan di
kolom.

------------------------------------------------------------------------

Dokumen ini menyesuaikan implementasi DB untuk Laravel.
