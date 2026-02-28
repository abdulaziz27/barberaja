<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\UserRole;
use App\Scopes\TenantScope;
use App\Services\BookingService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Booking::class);

        $query = Booking::query()
            ->with(['outlet', 'staff', 'product'])
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->orderBy('start_time', 'desc');

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        $bookings = $query->paginate(15);
        $outlets = Outlet::query()->orderBy('name')->get();

        return view('bookings.index', compact('bookings', 'outlets'));
    }

    public function create(): View
    {
        $this->authorize('create', Booking::class);

        $tenantId = TenantContext::id();
        $staffIds = UserRole::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->pluck('user_id')
            ->unique()
            ->values();

        $outlets = Outlet::query()->orderBy('name')->get();
        $staffUsers = User::query()->whereIn('id', $staffIds)->orderBy('name')->get();
        $products = Product::query()->services()->where('is_active', true)->orderBy('name')->get();

        return view('bookings.create', compact('outlets', 'staffUsers', 'products'));
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $product = Product::query()->findOrFail($request->product_id);
        if ($product->type !== Product::TYPE_SERVICE || ! $product->duration_minutes) {
            throw new \InvalidArgumentException('Produk harus layanan dengan durasi.');
        }

        $data = $request->validated();
        $data['duration_minutes'] = $product->duration_minutes;

        try {
            $this->bookingService->createBooking($data);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['start_time' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['start_time' => $e->getMessage()]);
        }

        return redirect()->route('bookings.index')
            ->with('success', 'Booking berhasil dibuat.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $this->bookingService->cancelBooking($booking);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('bookings.index')->with('error', $e->getMessage());
        }

        return redirect()->route('bookings.index')
            ->with('success', 'Booking telah dibatalkan.');
    }
}
