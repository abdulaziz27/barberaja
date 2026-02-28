<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppClient;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Booking;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Services\TicketService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Outlet $outlet;
    private User $staff;
    private Product $service;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Barbershop',
            'subscription_status' => 'active',
            'plan_type' => Tenant::PLAN_TYPE_FREE,
            'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
        ]);

        $this->outlet = Outlet::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Utama',
            'slug' => 'outlet-notif-test',
        ]);

        $this->staff = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff-notif@test.test',
            'password' => 'password',
        ]);

        UserRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->staff->id,
            'role' => 'staff',
        ]);

        $this->service = Product::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Haircut',
            'type' => Product::TYPE_SERVICE,
            'price' => 50000,
            'duration_minutes' => 30,
            'commission_type' => Product::COMMISSION_NONE,
            'commission_value' => 0,
            'is_active' => true,
        ]);

        TenantContext::set($this->tenant->id);

        $this->notificationService = app(NotificationService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    // ─── Template rendering ───────────────────────────────────────────────────

    public function test_render_template_replaces_variables(): void
    {
        $message = $this->notificationService->renderTemplate('booking_confirmed', [
            'customer_name' => 'Andi',
            'outlet_name' => 'Barbershop Keren',
            'date' => 'Senin, 01 Maret 2025',
            'time' => '10:00',
            'service_name' => 'Haircut',
            'staff_name' => 'Budi',
        ]);

        $this->assertStringContainsString('Andi', $message);
        $this->assertStringContainsString('Barbershop Keren', $message);
        $this->assertStringContainsString('10:00', $message);
        $this->assertStringContainsString('Haircut', $message);
        $this->assertStringNotContainsString(':customer_name', $message);
    }

    // ─── Job dispatched when booking confirmed ────────────────────────────────

    public function test_booking_confirmed_dispatches_wa_job_when_phone_present(): void
    {
        Queue::fake();

        $booking = Booking::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => now()->addDay()->setHour(10),
            'end_time' => now()->addDay()->setHour(10)->addMinutes(30),
            'duration_minutes' => 30,
            'source' => 'online',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
            'customer_name' => 'Andi',
            'customer_phone' => '081234567890',
        ]);

        $this->notificationService->notifyBookingConfirmed($booking);

        Queue::assertPushed(SendWhatsAppNotificationJob::class, function ($job) {
            return $job->eventType === 'booking_confirmed'
                && $job->phone === '081234567890';
        });
    }

    public function test_booking_confirmed_does_not_dispatch_when_no_phone(): void
    {
        Queue::fake();

        $booking = Booking::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => now()->addDay()->setHour(10),
            'end_time' => now()->addDay()->setHour(10)->addMinutes(30),
            'duration_minutes' => 30,
            'source' => 'kasir',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
            'customer_name' => null,
            'customer_phone' => null, // no phone
        ]);

        $this->notificationService->notifyBookingConfirmed($booking);

        Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
    }

    // ─── Job dispatched when ticket completed ─────────────────────────────────

    public function test_ticket_completed_dispatches_wa_job_when_booking_has_phone(): void
    {
        Queue::fake();

        $booking = Booking::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => now()->addDay()->setHour(10),
            'end_time' => now()->addDay()->setHour(10)->addMinutes(30),
            'duration_minutes' => 30,
            'source' => 'online',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
            'customer_name' => 'Andi',
            'customer_phone' => '081234567890',
        ]);

        $ticket = ServiceTicket::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'booking_id' => $booking->id,
            'staff_id' => $this->staff->id,
            'status' => ServiceTicket::STATUS_COMPLETED,
            'subtotal' => 50000,
            'total' => 50000,
            'dp_amount' => 0,
            'payment_status' => ServiceTicket::PAYMENT_STATUS_PAID,
        ]);

        $this->notificationService->notifyTicketCompleted($ticket);

        Queue::assertPushed(SendWhatsAppNotificationJob::class, function ($job) {
            return $job->eventType === 'ticket_completed'
                && $job->phone === '081234567890';
        });
    }

    // ─── Notification does not block main transaction ─────────────────────────

    public function test_notification_failure_does_not_block_booking_creation(): void
    {
        Queue::fake();

        // Bind a WhatsApp client that throws
        $this->app->bind(WhatsAppClient::class, function () {
            return new class implements WhatsAppClient {
                public function send(string $phone, string $message): bool
                {
                    throw new \RuntimeException('WA provider down');
                }
            };
        });

        // BookingService should still succeed even if notification dispatch fails
        $bookingService = app(BookingService::class);

        $booking = $bookingService->createBooking([
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => now()->addDay()->setHour(11)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'source' => 'online',
            'customer_name' => 'Andi',
            'customer_phone' => '081234567890',
        ]);

        // Booking was created successfully
        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    // ─── H-1 reminder ─────────────────────────────────────────────────────────

    public function test_booking_reminder_dispatches_wa_job(): void
    {
        Queue::fake();

        $booking = Booking::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $this->outlet->id,
            'staff_id' => $this->staff->id,
            'product_id' => $this->service->id,
            'start_time' => now()->addDay()->setHour(10),
            'end_time' => now()->addDay()->setHour(10)->addMinutes(30),
            'duration_minutes' => 30,
            'source' => 'online',
            'status' => Booking::STATUS_CONFIRMED,
            'dp_amount' => 0,
            'payment_status' => 'paid',
            'customer_name' => 'Andi',
            'customer_phone' => '081234567890',
        ]);

        $this->notificationService->notifyBookingReminder($booking);

        Queue::assertPushed(SendWhatsAppNotificationJob::class, function ($job) {
            return $job->eventType === 'booking_reminder'
                && $job->phone === '081234567890';
        });
    }
}
