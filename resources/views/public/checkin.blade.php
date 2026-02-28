<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check-in — {{ $outlet->name }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">

    <main class="max-w-sm mx-auto px-4 py-10">

        {{-- Outlet Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-sky-500/20 mb-4">
                <span class="text-3xl">✂️</span>
            </div>
            <h1 class="text-2xl font-bold text-white">{{ $outlet->name }}</h1>
            <p class="text-slate-400 text-sm mt-1">Self Check-in</p>
        </div>

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-900/40 border border-emerald-700 text-emerald-200 text-sm px-4 py-4 text-center">
                <p class="text-lg font-semibold mb-1">✅ Berhasil!</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-rose-900/40 border border-rose-700 text-rose-200 text-sm px-4 py-3">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($services->isNotEmpty())
            <form method="POST" action="{{ route('public.checkin.store', ['slug' => $outlet->slug]) }}" class="space-y-4">
                @csrf

                {{-- Nama --}}
                <div>
                    <label for="customer_name" class="block text-sm text-slate-400 mb-1">Nama Kamu</label>
                    <input id="customer_name" name="customer_name" type="text"
                        value="{{ old('customer_name') }}" placeholder="Contoh: Budi"
                        autocomplete="name"
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 text-slate-100 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 @error('customer_name') border-rose-500 @enderror">
                    @error('customer_name')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- No HP (opsional) --}}
                <div>
                    <label for="customer_phone" class="block text-sm text-slate-400 mb-1">No. HP <span class="text-slate-500">(opsional, untuk notif WA)</span></label>
                    <input id="customer_phone" name="customer_phone" type="tel"
                        value="{{ old('customer_phone') }}" placeholder="08xxxxxxxxxx"
                        autocomplete="tel"
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 text-slate-100 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                {{-- Pilih Layanan --}}
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Pilih Layanan</label>
                    <div class="space-y-2">
                        @foreach($services as $s)
                            <label class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 cursor-pointer hover:border-sky-500 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-500/10">
                                <input type="radio" name="product_id" value="{{ $s->id }}"
                                    {{ old('product_id') === $s->id ? 'checked' : '' }}
                                    class="accent-sky-500">
                                <div class="flex-1">
                                    <p class="font-medium text-white text-sm">{{ $s->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $s->duration_minutes }} menit</p>
                                </div>
                                <span class="text-sky-400 font-semibold text-sm">
                                    Rp {{ number_format($s->price, 0, ',', '.') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('product_id')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-4 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-900 font-bold text-base">
                        Masuk Antrian
                    </button>
                </div>

                <p class="text-center text-xs text-slate-500 pt-2">
                    Kapster akan dipilihkan otomatis berdasarkan ketersediaan.
                </p>
            </form>
        @else
            <div class="text-center py-8">
                <p class="text-slate-400">Belum ada layanan tersedia saat ini.</p>
                <p class="text-slate-500 text-sm mt-1">Silakan tanya ke kasir.</p>
            </div>
        @endif

    </main>

</body>
</html>
