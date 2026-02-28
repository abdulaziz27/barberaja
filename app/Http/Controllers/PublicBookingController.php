<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicBookingRequest;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\UserRole;
use App\Services\BookingService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    /**
     * Show the public booking page for an outlet identified by slug.
     * No authentication required.
     */
    public function show(string $slug): View
    {
        // Lookup outlet by slug globally (slug is unique across all tenants)
        $outlet = Outlet::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();

        // Load services (type=service, active) scoped to this outlet's tenant
        $services = Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->where('type', Product::TYPE_SERVICE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Load staff assigned to this outlet
        $staffIds = UserRole::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->whereIn('role', ['staff', 'manager'])
            ->pluck('user_id')
            ->unique()
            ->values();

        $staffUsers = User::query()
            ->whereIn('id', $staffIds)
            ->orderBy('name')
            ->get();

        return view('public.outlet', compact('outlet', 'services', 'staffUsers'));
    }

    /**
     * Handle public booking submission.
     * Sets tenant context from the outlet, then delegates to BookingService.
     */
    public function store(StorePublicBookingRequest $request, string $slug): RedirectResponse
    {
        $outlet = Outlet::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();

        // Validate that the product belongs to this outlet's tenant
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

        // Validate that the staff belongs to this outlet
        $staffBelongs = UserRole::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $outlet->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->where('user_id', $request->staff_id)
            ->whereIn('role', ['staff', 'manager'])
            ->exists();

        if (! $staffBelongs) {
            return back()->withInput()->withErrors(['staff_id' => 'Kapster tidak valid untuk outlet ini.']);
        }

        // Set tenant context for BookingService
        TenantContext::set($outlet->tenant_id);

        try {
            $this->bookingService->createBooking([
                'outlet_id' => $outlet->id,
                'staff_id' => $request->staff_id,
                'product_id' => $product->id,
                'start_time' => $request->start_time,
                'duration_minutes' => $product->duration_minutes,
                'source' => 'online',
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['start_time' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['start_time' => $e->getMessage()]);
        } finally {
            TenantContext::set(null);
        }

        return redirect()
            ->route('public.outlet.show', ['slug' => $slug])
            ->with('success', 'Booking berhasil! Kami akan menghubungi Anda untuk konfirmasi.');
    }
}
