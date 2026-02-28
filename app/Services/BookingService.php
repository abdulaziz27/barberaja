<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Check overlap per BOOKING_ENGINE_SPEC:
     * (start_time < existing_end_time) AND (end_time > existing_start_time) => conflict.
     */
    public function validateAvailability(
        string $outletId,
        string $staffId,
        Carbon $startTime,
        int $durationMinutes,
        ?string $excludeBookingId = null
    ): bool {
        $endTime = $startTime->copy()->addMinutes($durationMinutes);

        $query = Booking::query()
            ->where('outlet_id', $outletId)
            ->where('staff_id', $staffId)
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            });

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return ! $query->exists();
    }

    /**
     * Create booking (status=confirmed). No DP. All in transaction.
     * Dispatches WA notification after transaction commits (non-blocking).
     */
    public function createBooking(array $data): Booking
    {
        $booking = DB::transaction(function () use ($data): Booking {
            $startTime = Carbon::parse($data['start_time']);
            $durationMinutes = (int) $data['duration_minutes'];

            if ($durationMinutes < 1) {
                throw new \InvalidArgumentException('Durasi harus minimal 1 menit.');
            }

            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new \RuntimeException('Tenant context tidak tersedia.');
            }

            $available = $this->validateAvailability(
                $data['outlet_id'],
                $data['staff_id'],
                $startTime,
                $durationMinutes
            );

            if (! $available) {
                throw new \InvalidArgumentException('Slot tidak tersedia: bentrok dengan jadwal staff.');
            }

            $endTime = $startTime->copy()->addMinutes($durationMinutes);

            return Booking::query()->create([
                'tenant_id' => $tenantId,
                'outlet_id' => $data['outlet_id'],
                'staff_id' => $data['staff_id'],
                'product_id' => $data['product_id'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'source' => $data['source'] ?? Booking::SOURCE_KASIR,
                'status' => Booking::STATUS_CONFIRMED,
                'dp_amount' => 0,
                'payment_status' => 'paid',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
            ]);
        });

        // Dispatch WA notification AFTER transaction commits — non-blocking
        $this->notificationService->notifyBookingConfirmed($booking);

        return $booking;
    }

    /**
     * Cancel booking. All in transaction.
     */
    public function cancelBooking(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            if ($booking->isCancelled()) {
                throw new \InvalidArgumentException('Booking sudah dibatalkan.');
            }

            $booking->update(['status' => Booking::STATUS_CANCELLED]);

            return $booking->fresh();
        });
    }
}
