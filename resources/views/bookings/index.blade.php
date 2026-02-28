@extends('layouts.app')

@section('title', 'Booking')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Booking</h1>
        <a href="{{ route('bookings.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
            Buat Booking
        </a>
    </div>

    <form method="GET" action="{{ route('bookings.index') }}" class="mb-4 flex gap-2 items-end">
        <div>
            <label for="outlet_id" class="block text-xs text-slate-500 mb-1">Outlet</label>
            <select id="outlet_id" name="outlet_id" class="rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ request('outlet_id') === $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-sm">Filter</button>
    </form>

    <div class="rounded-xl border border-slate-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-800/50 text-slate-400 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Tanggal / Waktu</th>
                    <th class="px-4 py-3 font-medium">Outlet</th>
                    <th class="px-4 py-3 font-medium">Staff</th>
                    <th class="px-4 py-3 font-medium">Layanan</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium w-24">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($bookings as $booking)
                    <tr class="hover:bg-slate-800/30">
                        <td class="px-4 py-3">
                            {{ $booking->start_time->translatedFormat('d M Y') }}<br>
                            <span class="text-slate-500">{{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $booking->outlet?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->staff?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->product?->name ?? '–' }}</td>
                        <td class="px-4 py-3">
                            @switch($booking->status)
                                @case('confirmed')
                                    <span class="text-emerald-400">Dikonfirmasi</span>
                                    @break
                                @case('in_progress')
                                    <span class="text-amber-400">Berlangsung</span>
                                    @break
                                @case('completed')
                                    <span class="text-slate-500">Selesai</span>
                                    @break
                                @case('cancelled')
                                    <span class="text-rose-400">Dibatalkan</span>
                                    @break
                                @default
                                    {{ $booking->status }}
                            @endswitch
                        </td>
                        <td class="px-4 py-3">
                            @if($booking->status === 'confirmed' && auth()->user()->can('cancel', $booking))
                                <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="inline" onsubmit="return confirm('Batalkan booking ini?');">
                                    @csrf
                                    <button type="submit" class="text-rose-400 hover:text-rose-300 text-sm">Batal</button>
                                </form>
                            @else
                                –
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                            Belum ada booking. <a href="{{ route('bookings.create') }}" class="text-sky-400 hover:underline">Buat booking</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($bookings->hasPages())
        <div class="mt-4">
            {{ $bookings->links() }}
        </div>
    @endif
@endsection
