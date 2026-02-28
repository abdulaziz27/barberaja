<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $outlet->name }} — BarberAja</title>
    <meta name="description" content="Booking online di {{ $outlet->name }}. Pilih layanan, kapster, dan jadwal favoritmu.">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">

    {{-- Header --}}
    <header class="border-b border-slate-800">
        <div class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
            <span class="text-lg font-semibold text-white">BarberAja</span>
            <a href="{{ route('login') }}" class="text-sm text-slate-400 hover:text-white">Login</a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-8">

        {{-- Outlet Info --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">{{ $outlet->name }}</h1>
            @if($outlet->address)
                <p class="mt-1 text-slate-400 text-sm">{{ $outlet->address }}</p>
            @endif
        </div>

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-6 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 text-sm px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-rose-900/40 border border-rose-700 text-rose-200 text-sm px-4 py-3">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Services list --}}
        @if($services->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-slate-200 mb-3">Layanan Tersedia</h2>
                <div class="grid gap-3">
                    @foreach($services as $service)
                        <div class="rounded-lg border border-slate-800 bg-slate-900 px-4 py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-white">{{ $service->name }}</p>
                                <p class="text-sm text-slate-400">{{ $service->duration_minutes }} menit</p>
                            </div>
                            <span class="text-sky-400 font-semibold text-sm">
                                Rp {{ number_format($service->price, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <p class="text-slate-400 mb-8">Belum ada layanan tersedia saat ini.</p>
        @endif

        {{-- Booking Form --}}
        @if($services->isNotEmpty() && $staffUsers->isNotEmpty())
            <section>
                <h2 class="text-lg font-semibold text-slate-200 mb-4">Buat Booking</h2>

                <form method="POST" action="{{ route('public.outlet.book', ['slug' => $outlet->slug]) }}" class="space-y-4">
                    @csrf

                    {{-- Nama Pelanggan --}}
                    <div>
                        <label for="customer_name" class="block text-sm text-slate-400 mb-1">Nama Kamu</label>
                        <input id="customer_name" name="customer_name" type="text"
                            value="{{ old('customer_name') }}" placeholder="Contoh: Budi Santoso"
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('customer_name') border-rose-500 @enderror">
                        @error('customer_name')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- No HP (opsional) --}}
                    <div>
                        <label for="customer_phone" class="block text-sm text-slate-400 mb-1">No. HP <span class="text-slate-500">(opsional)</span></label>
                        <input id="customer_phone" name="customer_phone" type="tel"
                            value="{{ old('customer_phone') }}" placeholder="08xxxxxxxxxx"
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>

                    {{-- Pilih Layanan --}}
                    <div>
                        <label for="product_id" class="block text-sm text-slate-400 mb-1">Layanan</label>
                        <select id="product_id" name="product_id" required
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('product_id') border-rose-500 @enderror">
                            <option value="">Pilih layanan</option>
                            @foreach($services as $s)
                                <option value="{{ $s->id }}" {{ old('product_id') === $s->id ? 'selected' : '' }}>
                                    {{ $s->name }} ({{ $s->duration_minutes }} menit) — Rp {{ number_format($s->price, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Pilih Kapster --}}
                    <div>
                        <label for="staff_id" class="block text-sm text-slate-400 mb-1">Kapster</label>
                        <select id="staff_id" name="staff_id" required
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('staff_id') border-rose-500 @enderror">
                            <option value="">Pilih kapster</option>
                            @foreach($staffUsers as $u)
                                <option value="{{ $u->id }}" {{ old('staff_id') === $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('staff_id')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tanggal & Jam --}}
                    <div>
                        <label for="start_time" class="block text-sm text-slate-400 mb-1">Tanggal & Jam</label>
                        <input id="start_time" name="start_time" type="datetime-local"
                            value="{{ old('start_time') }}" required
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('start_time') border-rose-500 @enderror">
                        @error('start_time')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center px-4 py-3 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-semibold text-sm">
                            Booking Sekarang
                        </button>
                    </div>
                </form>
            </section>
        @elseif($staffUsers->isEmpty())
            <p class="text-slate-400">Belum ada kapster tersedia saat ini.</p>
        @endif

    </main>

    <footer class="mt-16 border-t border-slate-800 py-6 text-center text-xs text-slate-600">
        Powered by BarberAja
    </footer>

</body>
</html>
