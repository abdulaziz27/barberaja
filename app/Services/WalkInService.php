<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\UserRole;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WalkInService
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    /**
     * Find the next available slot across all staff in an outlet.
     *
     * Algorithm:
     * 1. Get all staff assigned to the outlet.
     * 2. For each staff, find the earliest time they are free (>= now).
     * 3. Return the staff + start_time with the earliest availability.
     *
     * @return array{staff: User, start_time: Carbon}|null  null if no staff available
     */
    public function findNextAvailableSlot(Outlet $outlet, int $durationMinutes): ?array
    {
        $staffIds = UserRole::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->whereIn('role', ['staff', 'manager'])
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($staffIds->isEmpty()) {
            return null;
        }

        $now = Carbon::now();
        $bestSlot = null; // ['staff' => User, 'start_time' => Carbon]

        foreach ($staffIds as $staffId) {
            $staff = User::query()->find($staffId);
            if (! $staff) {
                continue;
            }

            $earliestStart = $this->findEarliestSlotForStaff(
                $outlet->id,
                $staffId,
                $durationMinutes,
                $now
            );

            if ($bestSlot === null || $earliestStart->lt($bestSlot['start_time'])) {
                $bestSlot = [
                    'staff' => $staff,
                    'start_time' => $earliestStart,
                ];
            }
        }

        return $bestSlot;
    }

    /**
     * Find the earliest available start time for a specific staff member.
     * Starts from $from (usually now), then checks if the slot is free.
     * If not, advances to the end of the conflicting booking and tries again.
     */
    protected function findEarliestSlotForStaff(
        string $outletId,
        string $staffId,
        int $durationMinutes,
        Carbon $from
    ): Carbon {
        $candidate = $from->copy();

        // Max iterations to prevent infinite loop (e.g. 50 bookings ahead)
        for ($i = 0; $i < 50; $i++) {
            $endCandidate = $candidate->copy()->addMinutes($durationMinutes);

            // Check for any overlapping booking
            $conflict = Booking::query()
                ->withoutGlobalScopes()
                ->where('outlet_id', $outletId)
                ->where('staff_id', $staffId)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->where('start_time', '<', $endCandidate)
                ->where('end_time', '>', $candidate)
                ->orderBy('end_time', 'asc')
                ->first();

            if ($conflict === null) {
                // Slot is free
                return $candidate;
            }

            // Advance to end of conflicting booking
            $candidate = Carbon::parse($conflict->end_time);
        }

        // Fallback: return candidate after max iterations
        return $candidate;
    }

    /**
     * Create a walk-in booking for the next available slot.
     * Uses BookingService::createBooking() — overlap validation is enforced.
     *
     * @throws \InvalidArgumentException if no staff available or slot conflict
     */
    public function createWalkInBooking(
        Outlet $outlet,
        Product $service,
        ?string $customerName = null,
        ?string $customerPhone = null
    ): Booking {
        $tenantId = $outlet->tenant_id;
        TenantContext::set($tenantId);

        try {
            $slot = $this->findNextAvailableSlot($outlet, (int) $service->duration_minutes);

            if ($slot === null) {
                throw new \InvalidArgumentException('Tidak ada kapster yang tersedia saat ini.');
            }

            return $this->bookingService->createBooking([
                'outlet_id' => $outlet->id,
                'staff_id' => $slot['staff']->id,
                'product_id' => $service->id,
                'start_time' => $slot['start_time']->format('Y-m-d H:i:s'),
                'duration_minutes' => (int) $service->duration_minutes,
                'source' => Booking::SOURCE_QR,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
            ]);
        } finally {
            TenantContext::set(null);
        }
    }
}
