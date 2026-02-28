<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Booking;
use App\Models\ServiceTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches WhatsApp notification jobs without blocking the main transaction.
 * All dispatch calls are fire-and-forget via queue.
 */
class NotificationService
{
    /**
     * Notify customer that their booking is confirmed.
     * Dispatched after booking status = confirmed.
     * Does NOT throw — failure is logged and queued for retry.
     */
    public function notifyBookingConfirmed(Booking $booking): void
    {
        $phone = $booking->customer_phone ?? null;
        if (! $this->isValidPhone($phone)) {
            return;
        }

        $booking->load(['outlet', 'staff', 'product']);

        $message = $this->renderTemplate('booking_confirmed', [
            'customer_name' => $booking->customer_name ?? 'Pelanggan',
            'outlet_name' => $booking->outlet?->name ?? '-',
            'date' => Carbon::parse($booking->start_time)->translatedFormat('l, d F Y'),
            'time' => Carbon::parse($booking->start_time)->format('H:i'),
            'service_name' => $booking->product?->name ?? '-',
            'staff_name' => $booking->staff?->name ?? '-',
        ]);

        $this->dispatch($phone, $message, 'booking_confirmed', $booking->id, $booking->tenant_id);
    }

    /**
     * Notify customer of H-1 reminder.
     * Called by the daily cron command.
     */
    public function notifyBookingReminder(Booking $booking): void
    {
        $phone = $booking->customer_phone ?? null;
        if (! $this->isValidPhone($phone)) {
            return;
        }

        $booking->load(['outlet', 'staff', 'product']);

        $message = $this->renderTemplate('booking_reminder', [
            'customer_name' => $booking->customer_name ?? 'Pelanggan',
            'outlet_name' => $booking->outlet?->name ?? '-',
            'date' => Carbon::parse($booking->start_time)->translatedFormat('l, d F Y'),
            'time' => Carbon::parse($booking->start_time)->format('H:i'),
            'service_name' => $booking->product?->name ?? '-',
            'staff_name' => $booking->staff?->name ?? '-',
        ]);

        $this->dispatch($phone, $message, 'booking_reminder', $booking->id, $booking->tenant_id);
    }

    /**
     * Notify customer that their service ticket is completed.
     * Called after ticket status = completed.
     */
    public function notifyTicketCompleted(ServiceTicket $ticket): void
    {
        // Ticket may have customer_phone from the linked booking
        $phone = null;
        if ($ticket->booking_id) {
            $ticket->load('booking');
            $phone = $ticket->booking?->customer_phone ?? null;
        }

        if (! $this->isValidPhone($phone)) {
            return;
        }

        $ticket->load(['outlet', 'items.product']);

        $serviceSummary = $ticket->items
            ->map(fn ($item) => $item->product?->name ?? '-')
            ->filter()
            ->implode(', ');

        $message = $this->renderTemplate('ticket_completed', [
            'outlet_name' => $ticket->outlet?->name ?? '-',
            'service_summary' => $serviceSummary ?: '-',
            'total' => number_format((float) $ticket->total, 0, ',', '.'),
        ]);

        $this->dispatch($phone, $message, 'ticket_completed', $ticket->id, $ticket->tenant_id);
    }

    /**
     * Render a message template by replacing :variable placeholders.
     */
    public function renderTemplate(string $templateKey, array $variables): string
    {
        $template = config("whatsapp.templates.{$templateKey}", '');

        foreach ($variables as $key => $value) {
            $template = str_replace(':' . $key, (string) $value, $template);
        }

        return $template;
    }

    /**
     * Dispatch the notification job to the queue.
     * Never throws — failure is caught and logged.
     */
    protected function dispatch(
        string $phone,
        string $message,
        string $eventType,
        ?string $referenceId = null,
        ?string $tenantId = null
    ): void {
        try {
            SendWhatsAppNotificationJob::dispatch(
                phone: $phone,
                message: $message,
                eventType: $eventType,
                referenceId: $referenceId,
                tenantId: $tenantId,
            );
        } catch (\Throwable $e) {
            // Dispatch failure must not propagate to the caller
            Log::channel('whatsapp')->error('Failed to dispatch WhatsApp notification job.', [
                'phone' => $phone,
                'event_type' => $eventType,
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Basic phone validation: must be non-empty and contain only digits (optionally starting with +).
     */
    protected function isValidPhone(?string $phone): bool
    {
        if ($phone === null || trim($phone) === '') {
            return false;
        }

        return (bool) preg_match('/^\+?[0-9]{8,15}$/', trim($phone));
    }
}
