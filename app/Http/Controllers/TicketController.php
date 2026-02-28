<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddTicketItemRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\UserRole;
use App\Scopes\TenantScope;
use App\Services\TicketService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ServiceTicket::class);

        $query = ServiceTicket::query()
            ->with(['outlet', 'staff', 'items.product'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(15);
        $outlets = Outlet::query()->orderBy('name')->get();

        return view('tickets.index', compact('tickets', 'outlets'));
    }

    public function create(): View
    {
        $this->authorize('create', ServiceTicket::class);

        $tenantId = TenantContext::id();
        $staffIds = UserRole::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->pluck('user_id')
            ->unique()
            ->values();

        $outlets = Outlet::query()->orderBy('name')->get();
        $staffUsers = User::query()->whereIn('id', $staffIds)->orderBy('name')->get();

        return view('tickets.create', compact('outlets', 'staffUsers'));
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceTicket::class);

        $ticket = $this->ticketService->createTicket($request->validated());

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tiket berhasil dibuat. Tambah item lalu selesaikan.');
    }

    public function show(ServiceTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['outlet', 'staff', 'booking', 'items.product']);

        // Load all product types (service, bundle, retail) for the POS product grid
        $products = Product::query()
            ->where('is_active', true)
            ->whereIn('type', [Product::TYPE_SERVICE, Product::TYPE_BUNDLE, Product::TYPE_RETAIL])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return view('tickets.show', compact('ticket', 'products'));
    }

    public function addItem(AddTicketItemRequest $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->ticketService->addItem(
                $ticket,
                $request->validated('product_id'),
                (int) $request->validated('qty', 1)
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item ditambahkan.');
    }

    public function start(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->ticketService->startTicket($ticket);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tiket sedang dikerjakan.');
    }

    public function complete(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->ticketService->completeTicket($ticket);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tiket selesai. Komisi staff telah dihitung.');
    }

    public function cancel(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('cancel', $ticket);

        try {
            $this->ticketService->cancelTicket($ticket);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.index')
            ->with('success', 'Tiket dibatalkan.');
    }
}
