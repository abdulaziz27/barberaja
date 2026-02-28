@extends('layouts.app')

@section('title', 'Buat Tiket')

@section('content')
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="text-sm text-slate-400 hover:text-white">← Tiket / POS</a>
        <h1 class="text-2xl font-semibold mt-1">Buat Tiket</h1>
    </div>

    <form method="POST" action="{{ route('tickets.store') }}" class="max-w-md space-y-4">
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

        <p class="text-slate-500 text-sm">Tiket dari booking (opsional) bisa ditautkan nanti di halaman detail.</p>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
                Buat Tiket
            </button>
            <a href="{{ route('tickets.index') }}"
                class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 text-sm">
                Batal
            </a>
        </div>
    </form>
@endsection
