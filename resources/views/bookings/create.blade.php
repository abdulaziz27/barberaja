@extends('layouts.app')

@section('title', 'Buat Booking')

@section('content')
    <div class="mb-6">
        <a href="{{ route('bookings.index') }}" class="text-sm text-slate-400 hover:text-white">← Booking</a>
        <h1 class="text-2xl font-semibold mt-1">Buat Booking</h1>
    </div>

    <form method="POST" action="{{ route('bookings.store') }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="outlet_id" class="block text-sm text-slate-400 mb-1">Outlet</label>
            <select id="outlet_id" name="outlet_id" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('outlet_id') border-rose-500 @enderror">
                <option value="">Pilih outlet</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ old('outlet_id') === $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                @endforeach
            </select>
            @error('outlet_id')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="staff_id" class="block text-sm text-slate-400 mb-1">Staff / Kapster</label>
            <select id="staff_id" name="staff_id" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('staff_id') border-rose-500 @enderror">
                <option value="">Pilih staff</option>
                @foreach($staffUsers as $u)
                    <option value="{{ $u->id }}" {{ old('staff_id') === $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            @error('staff_id')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="product_id" class="block text-sm text-slate-400 mb-1">Layanan</label>
            <select id="product_id" name="product_id" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('product_id') border-rose-500 @enderror">
                <option value="">Pilih layanan</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" {{ old('product_id') === $p->id ? 'selected' : '' }}
                        data-duration="{{ $p->duration_minutes }}">
                        {{ $p->name }} ({{ $p->duration_minutes }} menit) — Rp {{ number_format($p->price, 0, ',', '.') }}
                    </option>
                @endforeach
            </select>
            @error('product_id')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="start_time" class="block text-sm text-slate-400 mb-1">Tanggal & jam mulai</label>
            <input id="start_time" name="start_time" type="datetime-local" value="{{ old('start_time') }}" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('start_time') border-rose-500 @enderror">
            @error('start_time')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
                Buat Booking
            </button>
            <a href="{{ route('bookings.index') }}"
                class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 text-sm">
                Batal
            </a>
        </div>
    </form>
@endsection
