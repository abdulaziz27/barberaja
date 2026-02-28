# barberaja.com

## IMPLEMENTATION_GUIDELINE_LARAVEL.md

Versi: 1.0

------------------------------------------------------------------------

# 1. Urutan Implementasi Disarankan

Phase 1: - Auth - Tenant & Outlet - Role & Policy - Product (service
only)

Phase 2: - Booking basic - Overlap validation - POS basic - Commission
basic

Phase 3: - DP Payment integration - Ledger implementation - Withdrawal

Phase 4: - Bundle logic - Reporting - Admin dashboard

------------------------------------------------------------------------

# 2. Coding Standard

-   Business logic di Service class
-   Controller hanya orchestration
-   Gunakan FormRequest untuk validation
-   Semua finansial logic dalam DB::transaction()

------------------------------------------------------------------------

# 3. Ledger Safety Rule

Buat LedgerService:

-   createEntry()
-   createBalancedEntry()
-   reverseEntry()

Jangan pernah insert ledger langsung dari controller.

------------------------------------------------------------------------

# 4. Booking Safety Rule

Buat BookingService:

-   validateAvailability()
-   createBooking()
-   cancelBooking()

Semua conflict logic di service.

------------------------------------------------------------------------

# 5. Withdrawal Safety Rule

Buat WithdrawalService:

-   validateBalance()
-   createWithdrawal()
-   handleSuccess()
-   handleFailure()

------------------------------------------------------------------------

# 6. Testing Strategy

Minimal:

-   Unit test ledger balancing
-   Unit test booking overlap
-   Unit test withdrawal negative prevention
-   Integration test webhook idempotency

------------------------------------------------------------------------

# 7. Deployment Strategy

Awal:

-   Single server
-   PostgreSQL managed
-   Daily DB backup

Scale:

-   Redis queue
-   Read replica
-   Monitoring escrow reconciliation

------------------------------------------------------------------------

Dokumen ini menjadi panduan eksekusi Laravel implementation
barberaja.com.
