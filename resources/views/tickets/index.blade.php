@extends('layouts.app')

@section('title', 'Tiket / POS')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Tiket / POS</h1>
        <a href="{{ route('tickets.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
            Buat Tiket
        </a>
    </div>

    <form method="GET" action="{{ route('tickets.index') }}" class="mb-4 flex gap-2 items-end flex-wrap">
        <div>
            <label for="outlet_id" class="block text-xs text-slate-500 mb-1">Outlet</label>
            <select id="outlet_id" name="outlet_id" class="rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ request('outlet_id') === $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-xs text-slate-500 mb-1">Status</label>
            <select id="status" name="status" class="rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm">
                <option value="">Semua</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>Sedang dikerjakan</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-sm">Filter</button>
    </form>

    <div class="rounded-xl border border-slate-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-800/50 text-slate-400 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Waktu</th>
                    <th class="px-4 py-3 font-medium">Outlet</th>
                    <th class="px-4 py-3 font-medium">Staff</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="px-4 py-3 font-medium">Komisi</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium w-24">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($tickets as $ticket)
                    <tr class="hover:bg-slate-800/30">
                        <td class="px-4 py-3">
                            {{ $ticket->created_at->translatedFormat('d M Y H:i') }}
                        </td>
                        <td class="px-4 py-3">{{ $ticket->outlet?->name }}</td>
                        <td class="px-4 py-3">{{ $ticket->staff?->name }}</td>
                        <td class="px-4 py-3">Rp {{ number_format($ticket->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if($ticket->staff_commission !== null)
                                Rp {{ number_format($ticket->staff_commission, 0, ',', '.') }}
                            @else
                                –
                            @endif
                        </td>
                        <td class="px-4 py-3">
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
                        </td>
                        <td class="px-4 py-3">
                            @if(!$ticket->isCompleted() && !$ticket->isCancelled())
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-sky-400 hover:text-sky-300 text-sm">Buka</a>
                            @else
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-slate-400 hover:text-white text-sm">Lihat</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                            Belum ada tiket. <a href="{{ route('tickets.create') }}" class="text-sky-400 hover:underline">Buat tiket</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->hasPages())
        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    @endif
@endsection
