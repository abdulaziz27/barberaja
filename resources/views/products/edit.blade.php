@extends('layouts.app')

@section('title', 'Edit Layanan')

@section('content')
    <div class="mb-6">
        <a href="{{ route('products.index') }}" class="text-sm text-slate-400 hover:text-white">← Layanan</a>
        <h1 class="text-2xl font-semibold mt-1">Edit Layanan</h1>
    </div>

    <form method="POST" action="{{ route('products.update', $product) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm text-slate-400 mb-1">Nama layanan</label>
            <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('name') border-rose-500 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="price" class="block text-sm text-slate-400 mb-1">Harga (Rp)</label>
            <input id="price" name="price" type="number" min="0" step="1000" value="{{ old('price', $product->price) }}" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('price') border-rose-500 @enderror">
            @error('price')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="duration_minutes" class="block text-sm text-slate-400 mb-1">Durasi (menit)</label>
            <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="480" value="{{ old('duration_minutes', $product->duration_minutes) }}" required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('duration_minutes') border-rose-500 @enderror">
            @error('duration_minutes')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="commission_type" class="block text-sm text-slate-400 mb-1">Tipe komisi</label>
            <select id="commission_type" name="commission_type"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                <option value="none" {{ old('commission_type', $product->commission_type) === 'none' ? 'selected' : '' }}>Tidak ada</option>
                <option value="percentage" {{ old('commission_type', $product->commission_type) === 'percentage' ? 'selected' : '' }}>Persen</option>
                <option value="fixed" {{ old('commission_type', $product->commission_type) === 'fixed' ? 'selected' : '' }}>Tetap (Rp)</option>
            </select>
        </div>

        <div>
            <label for="commission_value" class="block text-sm text-slate-400 mb-1">Nilai komisi (opsional)</label>
            <input id="commission_value" name="commission_value" type="number" min="0" step="0.01" value="{{ old('commission_value', $product->commission_value) }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
            @error('commission_value')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}
                class="rounded border-slate-700 bg-slate-900 text-sky-500 focus:ring-sky-500">
            <label for="is_active" class="text-sm text-slate-400">Aktif</label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
                Simpan
            </button>
            <a href="{{ route('products.index') }}"
                class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 text-sm">
                Batal
            </a>
        </div>
    </form>
@endsection
