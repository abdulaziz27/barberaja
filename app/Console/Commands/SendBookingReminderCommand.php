<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingReminderCommand extends Command
{
    protected $signature = 'barberaja:send-booking-reminders
                            {--date= : Target date (Y-m-d). Defaults to tomorrow.}';

    protected $description = 'Send H-1 WhatsApp reminders for bookings scheduled for tomorrow.';

    public function handle(NotificationService $notificationService): int
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::tomorrow();

        $this->info("Sending reminders for bookings on {$targetDate->toDateString()}...");

        $bookings = Booking::query()
            ->withoutGlobalScopes()
            ->whereDate('start_time', $targetDate->toDateString())
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING_PAYMENT])
            ->whereNotNull('customer_phone')
            ->with(['outlet', 'staff', 'product'])
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $notificationService->notifyBookingReminder($booking);
            $count++;
        }

        $this->info("Dispatched {$count} reminder notification(s).");

        return self::SUCCESS;
    }
}
