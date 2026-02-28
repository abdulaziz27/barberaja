@extends('layouts.app')

@section('title', 'Layanan')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Layanan</h1>
        <a href="{{ route('products.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-medium text-sm">
            Tambah Layanan
        </a>
    </div>

    <div class="rounded-xl border border-slate-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-800/50 text-slate-400 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Harga</th>
                    <th class="px-4 py-3 font-medium">Durasi</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium w-32">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($products as $product)
                    <tr class="hover:bg-slate-800/30">
                        <td class="px-4 py-3">{{ $product->name }}</td>
                        <td class="px-4 py-3">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $product->duration_minutes }} menit</td>
                        <td class="px-4 py-3">
                            @if($product->is_active)
                                <span class="text-emerald-400">Aktif</span>
                            @else
                                <span class="text-slate-500">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 flex gap-2">
                            <a href="{{ route('products.edit', $product) }}"
                                class="text-sky-400 hover:text-sky-300">Edit</a>
                            <form method="POST" action="{{ route('products.destroy', $product) }}"
                                onsubmit="return confirm('Hapus layanan ini?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-400 hover:text-rose-300">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                            Belum ada layanan. <a href="{{ route('products.create') }}" class="text-sky-400 hover:underline">Tambah layanan</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($products->hasPages())
        <div class="mt-4">
            {{ $products->links() }}
        </div>
    @endif
@endsection
