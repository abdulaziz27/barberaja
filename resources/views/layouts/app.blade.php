<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - BarberAja</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <nav class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-300 hover:text-white">Dashboard</a>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-slate-300 hover:text-white">Layanan</a>
                <a href="{{ route('bookings.index') }}" class="text-sm font-medium text-slate-300 hover:text-white">Booking</a>
                <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-slate-300 hover:text-white">Tiket / POS</a>
                @can('platformAdmin')
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-amber-400 hover:text-amber-300">Admin</a>
                @endcan
            </nav>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-slate-400 hover:text-white">Logout</button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 text-sm px-4 py-3">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg bg-rose-900/40 border border-rose-700 text-rose-200 text-sm px-4 py-3">
                {{ session('error') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
