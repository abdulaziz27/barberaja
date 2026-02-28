<?php

namespace App\Jobs;

use App\Contracts\WhatsAppClient;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries;

    /**
     * Number of seconds to wait before retrying the job.
     */
    public int $backoff;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $eventType,   // booking_confirmed | booking_reminder | ticket_completed
        public readonly ?string $referenceId = null, // booking_id or ticket_id for idempotency
        public readonly ?string $tenantId = null,
    ) {
        $this->tries = config('whatsapp.max_attempts', 3);
        $this->backoff = config('whatsapp.retry_after_seconds', 60);
        $this->onQueue(config('whatsapp.queue', 'notifications'));
    }

    /**
     * Rate limiting middleware: max N messages per minute per phone number.
     */
    public function middleware(): array
    {
        $limit = config('whatsapp.rate_limit_per_minute', 5);
        $key = 'whatsapp:' . $this->phone;

        return [
            new RateLimited($key),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppClient $client): void
    {
        try {
            $sent = $client->send($this->phone, $this->message);

            if (! $sent) {
                Log::channel('whatsapp')->warning('WhatsApp send returned false.', [
                    'phone' => $this->phone,
                    'event_type' => $this->eventType,
                    'reference_id' => $this->referenceId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('WhatsApp send failed.', [
                'phone' => $this->phone,
                'event_type' => $this->eventType,
                'reference_id' => $this->referenceId,
                'error' => $e->getMessage(),
            ]);

            // Re-throw so the queue worker can retry
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('whatsapp')->error('WhatsApp job permanently failed.', [
            'phone' => $this->phone,
            'event_type' => $this->eventType,
            'reference_id' => $this->referenceId,
            'error' => $exception->getMessage(),
        ]);

        // Log to audit log for visibility (optional meta)
        try {
            AuditLog::log(
                'whatsapp_notification_failed',
                null,
                $this->referenceId,
                null,
                null,
                [
                    'tenant_id' => $this->tenantId,
                    'phone' => $this->phone,
                    'event_type' => $this->eventType,
                    'error' => $exception->getMessage(),
                ]
            );
        } catch (\Throwable) {
            // Audit log failure must not cause further issues
        }
    }
}
