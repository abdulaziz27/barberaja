@extends('layouts.app')

@section('title', 'Tiket #' . substr($ticket->id, 0, 8))

@section('content')
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="text-sm text-slate-400 hover:text-white">← Tiket / POS</a>
        <h1 class="text-2xl font-semibold mt-1">
            Tiket — {{ $ticket->outlet?->name }} · {{ $ticket->staff?->name }}
            <span class="text-slate-500 font-normal text-base">
                @switch($ticket->status)
                    @case('open')
                        <span class="text-sky-400">Open</span>
                        @break
                    @case('in_progress')
                        <span class="text-amber-400">Sedang dikerjakan</span>
                        @break
                    @case('completed')
                        <span class="text-emerald-400">Selesai</span>
                        @break
                    @case('cancelled')
                        <span class="text-rose-400">Dibatalkan</span>
                        @break
                    @default
                        {{ $ticket->status }}
                @endswitch
            </span>
        </h1>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-rose-900/40 border border-rose-700 text-rose-200 text-sm px-4 py-3">
            {{ session('error') }}
        </div>
    @endif
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 text-sm px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-800 overflow-hidden">
                <div class="bg-slate-800/50 px-4 py-2 text-slate-400 text-sm font-medium">Item</div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/30 text-slate-500 text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Layanan</th>
                            <th class="px-4 py-2 font-medium text-right">Qty</th>
                            <th class="px-4 py-2 font-medium text-right">Harga</th>
                            <th class="px-4 py-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($ticket->items as $item)
                            <tr>
                                <td class="px-4 py-2">{{ $item->product?->name }}</td>
                                <td class="px-4 py-2 text-right">{{ $item->qty }}</td>
                                <td class="px-4 py-2 text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-slate-500">Belum ada item.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t border-slate-800 px-4 py-3 flex justify-between text-sm">
                    <span class="text-slate-400">Subtotal / Total</span>
                    <span class="font-medium">Rp {{ number_format($ticket->total, 0, ',', '.') }}</span>
                </div>
                @if($ticket->staff_commission !== null)
                    <div class="border-t border-slate-800 px-4 py-2 flex justify-between text-sm text-slate-400">
                        <span>Komisi staff</span>
                        <span>Rp {{ number_format($ticket->staff_commission, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>

            @if($ticket->canAddItems())
                <form method="POST" action="{{ route('tickets.addItem', $ticket) }}" class="rounded-xl border border-slate-800 p-4 flex flex-wrap gap-2 items-end">
                    @csrf
                    <div class="flex-1 min-w-[180px]">
                        <label for="product_id" class="block text-xs text-slate-500 mb-1">Tambah layanan</label>
                        <select id="product_id" name="product_id" required
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm">
                            <option value="">Pilih layanan</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} — Rp {{ number_format($p->price, 0, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-20">
                        <label for="qty" class="block text-xs text-slate-500 mb-1">Qty</label>
                        <input id="qty" name="qty" type="number" min="1" max="99" value="1"
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
                        Tambah
                    </button>
                </form>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            @if($ticket->isOpen())
                <form method="POST" action="{{ route('tickets.start', $ticket) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 font-medium text-sm">
                        Mulai kerjakan
                    </button>
                </form>
            @endif

            @if($ticket->canComplete())
                <form method="POST" action="{{ route('tickets.complete', $ticket) }}" class="inline" onsubmit="return confirm('Selesaikan tiket dan hitung komisi?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-medium text-sm">
                        Selesaikan tiket
                    </button>
                </form>
            @endif

            @if(!$ticket->isCompleted() && !$ticket->isCancelled() && auth()->user()->can('cancel', $ticket))
                <form method="POST" action="{{ route('tickets.cancel', $ticket) }}" class="inline" onsubmit="return confirm('Batalkan tiket ini?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg border border-rose-700 text-rose-400 hover:bg-rose-900/30 text-sm">
                        Batalkan tiket
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
