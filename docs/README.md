# BarberAja Docs Index

Dokumen-dokumen ini adalah **source of truth** untuk scope, arsitektur, dan invariant sistem.

## Product / Vision (referensi)

- `docs/01_VISION_AND_SCOPE.md`
- `docs/02_PRODUCT_REQUIREMENTS.md`
- `docs/08_ADMIN_PLATFORM_SPEC.md`

## Technical invariants (wajib dipatuhi)

- `docs/UPDATED_SYSTEM_ARCHITECTURE_LARAVEL.md`
- `docs/UPDATED_DATABASE_SCHEMA_LARAVEL.md`
- `docs/UPDATED_DATABASE_SCHEMA_LARAVEL.md` (konvensi Laravel: UUID, soft delete policy, ledger rules)
- `docs/IMPLEMENTATION_GUIDELINE_LARAVEL.md` (urutan implementasi + safety rules)
- `docs/05_LEDGER_AND_PAYMENT_SPEC.md` (dokumen paling kritikal)
- `docs/06_BOOKING_ENGINE_SPEC.md` (jantung scheduling)
- `docs/07_ROLE_AND_PERMISSION_MATRIX.md` (mencegah data leakage & privilege escalation)

## Cara pakai (untuk AI & tim)

- Jika menyentuh **uang/ledger/payment/withdrawal/refund/webhook**: baca `05_LEDGER_AND_PAYMENT_SPEC.md` dulu.
- Jika menyentuh **booking/scheduling/walk-in**: baca `06_BOOKING_ENGINE_SPEC.md` dulu.
- Jika menyentuh **multi-tenant / authz**: baca `UPDATED_SYSTEM_ARCHITECTURE_LARAVEL.md` + `07_ROLE_AND_PERMISSION_MATRIX.md`.
