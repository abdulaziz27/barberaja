# barberaja.com

## DATABASE_SCHEMA.md

Versi: 1.0 Status: Final Draft Target: Backend & Engineering

------------------------------------------------------------------------

# 1. Tujuan Dokumen

Dokumen ini mendefinisikan struktur database utama sistem barberaja.com,
termasuk:

-   Tabel & field
-   Relationship antar tabel
-   Constraint & index
-   Invariant bisnis
-   Strategi RLS (Row Level Security)

Database: PostgreSQL (Supabase)

------------------------------------------------------------------------

# 2. Prinsip Umum Database

1.  Multi-tenant via tenant_id
2.  Ledger bersifat immutable (insert-only)
3.  Tidak ada saldo disimpan langsung (derived from ledger)
4.  Semua tabel transaksi memiliki created_at
5.  Semua tabel utama memiliki index pada tenant_id
6.  Gunakan foreign key constraint eksplisit

------------------------------------------------------------------------

# 3. Core Tables

------------------------------------------------------------------------

## 3.1 tenants

Mewakili Owner.

Fields: - id (uuid, pk) - name (text) - subscription_status (active,
suspended, cancelled) - created_at (timestamp)

Index: - pk(id)

------------------------------------------------------------------------

## 3.2 outlets

Fields: - id (uuid, pk) - tenant_id (uuid, fk -\> tenants.id) - name
(text) - slug (text, unique) - address (text) - latitude (decimal) -
longitude (decimal) - require_dp (boolean) - allow_walkin (boolean) -
created_at (timestamp)

Index: - pk(id) - index(tenant_id) - unique(slug)

------------------------------------------------------------------------

## 3.3 users

Fields: - id (uuid, pk) - email (text, unique) - created_at (timestamp)

------------------------------------------------------------------------

## 3.4 user_roles

Fields: - id (uuid, pk) - user_id (uuid, fk -\> users.id) - tenant_id
(uuid, fk -\> tenants.id) - outlet_id (uuid, nullable) - role (owner,
manager, staff, platform_admin)

Index: - index(user_id) - index(tenant_id)

------------------------------------------------------------------------

# 4. Catalog Tables

------------------------------------------------------------------------

## 4.1 products

Fields: - id (uuid, pk) - tenant_id (uuid) - outlet_id (uuid) - name
(text) - type (service, retail, bundle) - price (numeric) -
duration_minutes (integer, nullable for retail) - stock_qty (integer,
nullable for service) - commission_type (percentage, fixed, none) -
commission_value (numeric) - is_active (boolean) - created_at
(timestamp)

Index: - index(tenant_id) - index(outlet_id)

Constraint: - duration_minutes required if type=service - stock_qty
required if type=retail

------------------------------------------------------------------------

## 4.2 product_bundle_items

Fields: - id (uuid, pk) - bundle_id (uuid, fk -\> products.id) -
product_id (uuid, fk -\> products.id) - qty (integer)

Constraint: - bundle_id must reference product.type = bundle -
product_id cannot reference another bundle

------------------------------------------------------------------------

# 5. Booking & Ticket Tables

------------------------------------------------------------------------

## 5.1 bookings

Fields: - id (uuid, pk) - tenant_id (uuid) - outlet_id (uuid) - staff_id
(uuid) - start_time (timestamp) - end_time (timestamp) - source (online,
qr, kasir) - status (pending_payment, confirmed, in_progress, completed,
cancelled, no_show) - dp_amount (numeric) - payment_status (pending,
paid, failed) - created_at (timestamp)

Index: - index(tenant_id) - index(outlet_id) - index(staff_id) -
index(start_time)

Constraint: - no overlapping booking per staff (enforced via
application + exclusion constraint if needed)

------------------------------------------------------------------------

## 5.2 service_tickets

Fields: - id (uuid, pk) - tenant_id (uuid) - outlet_id (uuid) -
booking_id (uuid, nullable) - staff_id (uuid) - status (open,
in_progress, completed, cancelled) - subtotal (numeric) - total
(numeric) - dp_amount (numeric) - payment_status (pending, paid) -
created_at (timestamp)

Index: - index(tenant_id) - index(outlet_id)

------------------------------------------------------------------------

## 5.3 ticket_items

Fields: - id (uuid, pk) - ticket_id (uuid, fk -\> service_tickets.id) -
product_id (uuid, fk -\> products.id) - qty (integer) - price
(numeric) - total (numeric)

Index: - index(ticket_id)

------------------------------------------------------------------------

# 6. Payment & Ledger

------------------------------------------------------------------------

## 6.1 ledger_entries (IMMUTABLE)

Fields: - id (uuid, pk) - tenant_id (uuid) - outlet_id (uuid,
nullable) - entry_type (debit, credit) - account (platform_revenue,
owner_escrow, subscription) - amount (numeric) - reference_type
(booking, withdrawal, subscription, refund) - reference_id (uuid) -
created_at (timestamp)

Rules: - Insert only - No update/delete - Each logical transaction must
balance

Index: - index(tenant_id) - index(reference_id)

------------------------------------------------------------------------

## 6.2 withdrawals

Fields: - id (uuid, pk) - tenant_id (uuid) - amount (numeric) - status
(pending, success, failed, reversed) - bank_name (text) -
bank_account_number (text) - created_at (timestamp)

Index: - index(tenant_id) - index(status)

------------------------------------------------------------------------

# 7. Derived Values (Tidak Disimpan Langsung)

Owner Escrow Balance:

SUM(credit owner_escrow) - SUM(debit owner_escrow)

Platform Revenue:

SUM(credit platform_revenue)

------------------------------------------------------------------------

# 8. RLS Strategy

Rule dasar:

user_roles.user_id = auth.uid() AND tenant_id = current tenant context

Staff hanya boleh akses outlet_id sesuai role.

Platform admin bypass via service role.

------------------------------------------------------------------------

# 9. Invariant Rules

1.  Ledger immutable
2.  Withdrawal tidak boleh \> escrow balance
3.  Tidak boleh ada booking overlap staff
4.  Retail stock tidak boleh minus
5.  Bundle tidak boleh nested
6.  Escrow global harus match Xendit balance

------------------------------------------------------------------------

Dokumen ini adalah sumber kebenaran struktur data barberaja.com.
