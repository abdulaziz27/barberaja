<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQrCheckInRequest;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\WalkInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QrCheckInController extends Controller
{
    public function __construct(
        private WalkInService $walkInService
    ) {}

    /**
     * Show the QR self check-in page for an outlet.
     * Accessed by scanning the QR poster at the outlet.
     */
    public function show(string $slug): View
    {
        $outlet = Outlet::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();

        // Only show services (type=service, active) for this outlet
        $services = Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->where('type', Product::TYPE_SERVICE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('public.checkin', compact('outlet', 'services'));
    }

    /**
     * Handle QR check-in submission.
     * Auto-assigns next available staff and creates a walk-in booking.
     */
    public function store(StoreQrCheckInRequest $request, string $slug): RedirectResponse
    {
        $outlet = Outlet::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();

        // Validate product belongs to this outlet's tenant
        $product = Product::query()
            ->withoutGlobalScopes()
            ->where('id', $request->product_id)
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->where('type', Product::TYPE_SERVICE)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return back()->withInput()->withErrors(['product_id' => 'Layanan tidak valid untuk outlet ini.']);
        }

        try {
            $booking = $this->walkInService->createWalkInBooking(
                outlet: $outlet,
                service: $product,
                customerName: $request->customer_name,
                customerPhone: $request->customer_phone,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product_id' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['product_id' => $e->getMessage()]);
        }

        $startTime = \Carbon\Carbon::parse($booking->start_time);

        return redirect()
            ->route('public.checkin.show', ['slug' => $slug])
            ->with('success', "Check-in berhasil! Estimasi giliran kamu: {$startTime->format('H:i')}. Silakan tunggu di tempat.");
    }
}
