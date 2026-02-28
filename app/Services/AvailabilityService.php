<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\UserRole;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Business hours: 08:00 – 21:00 (configurable later).
     */
    public const OPEN_HOUR = 8;
    public const CLOSE_HOUR = 21;

    /**
     * Slot interval in minutes (e.g. 30 = slots at :00 and :30).
     */
    public const SLOT_INTERVAL_MINUTES = 30;

    /**
     * Get available time slots for a specific staff member on a given date.
     *
     * Returns an array of slot objects:
     * [
     *   ['time' => '09:00', 'datetime' => '2025-03-01 09:00:00', 'available' => true],
     *   ...
     * ]
     *
     * @return array<int, array{time: string, datetime: string, available: bool}>
     */
    public function getSlotsForStaff(
        string $outletId,
        string $staffId,
        Carbon $date,
        int $durationMinutes
    ): array {
        $slots = [];
        $now = Carbon::now();

        // Generate all slots for the day
        $current = $date->copy()->setHour(self::OPEN_HOUR)->setMinute(0)->setSecond(0);
        $closeTime = $date->copy()->setHour(self::CLOSE_HOUR)->setMinute(0)->setSecond(0);

        // Fetch all bookings for this staff on this date (one query)
        $existingBookings = Booking::query()
            ->withoutGlobalScopes()
            ->where('outlet_id', $outletId)
            ->where('staff_id', $staffId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->whereDate('start_time', $date->toDateString())
            ->get(['start_time', 'end_time']);

        while ($current->copy()->addMinutes($durationMinutes)->lte($closeTime)) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);

            // Skip past slots (with 5-minute buffer)
            $available = $current->gt($now->copy()->addMinutes(5));

            // Check overlap with existing bookings
            if ($available) {
                foreach ($existingBookings as $booking) {
                    $bookingStart = Carbon::parse($booking->start_time);
                    $bookingEnd = Carbon::parse($booking->end_time);

                    // Overlap: slot_start < booking_end AND slot_end > booking_start
                    if ($current->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
                        $available = false;
                        break;
                    }
                }
            }

            $slots[] = [
                'time' => $current->format('H:i'),
                'datetime' => $current->format('Y-m-d H:i:s'),
                'available' => $available,
            ];

            $current->addMinutes(self::SLOT_INTERVAL_MINUTES);
        }

        return $slots;
    }

    /**
     * Get available slots for all staff in an outlet on a given date.
     * Returns a map of staff_id => slots array.
     *
     * @return array<string, array{staff: array{id: string, name: string}, slots: array}>
     */
    public function getSlotsForOutlet(Outlet $outlet, Carbon $date, int $durationMinutes): array
    {
        $staffIds = UserRole::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->whereIn('role', ['staff', 'manager'])
            ->pluck('user_id')
            ->unique()
            ->values();

        $result = [];

        foreach ($staffIds as $staffId) {
            $staff = User::query()->find($staffId);
            if (! $staff) {
                continue;
            }

            $slots = $this->getSlotsForStaff($outlet->id, $staffId, $date, $durationMinutes);

            $result[$staffId] = [
                'staff' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                ],
                'slots' => $slots,
            ];
        }

        return $result;
    }
}
