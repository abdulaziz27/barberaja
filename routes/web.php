<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\XenditWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::post('/webhooks/xendit/dp', [XenditWebhookController::class, 'handleDpPayment'])
    ->name('webhooks.xendit.dp');
Route::post('/webhooks/xendit/disbursement', [XenditWebhookController::class, 'handleDisbursement'])
    ->name('webhooks.xendit.disbursement');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('admin/dashboard', [AdminDashboardController::class, 'index'])
    ->middleware(['auth', 'can:platformAdmin'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::resource('products', ProductController::class)->except('show');

    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/items', [TicketController::class, 'addItem'])->name('tickets.addItem');
    Route::post('tickets/{ticket}/start', [TicketController::class, 'start'])->name('tickets.start');
    Route::post('tickets/{ticket}/complete', [TicketController::class, 'complete'])->name('tickets.complete');
    Route::post('tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');
});
