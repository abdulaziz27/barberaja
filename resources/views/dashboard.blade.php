@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
        @if(isset($metrics['plan_info']) && $metrics['plan_info']['is_overdue'] ?? false)
            <div class="rounded-lg bg-rose-900/40 border border-rose-700 text-rose-300 text-xs px-3 py-2">
                ⚠️ Subscription overdue
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         ROW 1 — Key Metrics
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Revenue Today --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Revenue Hari Ini</p>
            <p class="text-2xl font-bold text-white mt-2">
                Rp {{ number_format($metrics['revenue_today'] ?? 0, 0, ',', '.') }}
            </p>
            <p class="text-xs text-slate-500 mt-1">
                {{ $metrics['tickets_today'] ?? 0 }} tiket selesai
            </p>
        </div>

        {{-- Revenue This Month --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Revenue Bulan Ini</p>
            <p class="text-2xl font-bold text-white mt-2">
                Rp {{ number_format($metrics['revenue_month'] ?? 0, 0, ',', '.') }}
            </p>
            <p class="text-xs text-slate-500 mt-1">
                {{ $metrics['tickets_month'] ?? 0 }} tiket selesai
            </p>
        </div>

        {{-- Escrow Balance --}}
        <div class="rounded-2xl border border-sky-800/50 bg-sky-900/20 p-5">
            <p class="text-xs text-sky-400 font-medium uppercase tracking-wide">Saldo Escrow</p>
            <p class="text-2xl font-bold text-white mt-2">
                Rp {{ number_format($metrics['escrow_balance'] ?? 0, 0, ',', '.') }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Tersedia untuk withdrawal</p>
        </div>

        {{-- Plan Status --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Plan</p>
            <p class="text-xl font-bold text-white mt-2 capitalize">
                {{ $metrics['plan_info']['plan_type'] ?? 'free' }}
            </p>
            <p class="text-xs mt-1 {{ ($metrics['plan_info']['is_overdue'] ?? false) ? 'text-rose-400' : 'text-emerald-400' }}">
                {{ ($metrics['plan_info']['is_overdue'] ?? false) ? '⚠️ Overdue' : '✅ Aktif' }}
            </p>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         ROW 2 — Revenue Chart (last 7 days)
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-300">Revenue 7 Hari Terakhir</h2>
            <span class="text-xs text-slate-500">Tiket selesai</span>
        </div>

        <div id="revenue-chart" class="h-40 w-full" x-data="revenueChart()" x-init="init()">
            <canvas id="chart-canvas" class="w-full h-full"></canvas>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         ROW 3 — Top Services + Top Staff
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Top Services --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-sm font-semibold text-slate-300 mb-4">Top Layanan (Bulan Ini)</h2>
            @if(!empty($metrics['top_services']))
                <div class="space-y-3">
                    @foreach($metrics['top_services'] as $i => $service)
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-slate-400 text-xs font-bold flex items-center justify-center shrink-0">
                            {{ $i + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $service['name'] }}</p>
                            <p class="text-xs text-slate-500">{{ $service['count'] }}× terjual</p>
                        </div>
                        <span class="text-sm font-semibold text-sky-400 shrink-0">
                            Rp {{ number_format($service['total_revenue'], 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 text-sm">Belum ada data bulan ini.</p>
            @endif
        </div>

        {{-- Top Staff --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-sm font-semibold text-slate-300 mb-4">Top Kapster (Bulan Ini)</h2>
            @if(!empty($metrics['top_staff']))
                <div class="space-y-3">
                    @foreach($metrics['top_staff'] as $i => $staff)
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-slate-400 text-xs font-bold flex items-center justify-center shrink-0">
                            {{ $i + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $staff['name'] }}</p>
                            <p class="text-xs text-slate-500">{{ $staff['ticket_count'] }} tiket</p>
                        </div>
                        <span class="text-sm font-semibold text-emerald-400 shrink-0">
                            Rp {{ number_format($staff['total_commission'], 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 text-sm">Belum ada data bulan ini.</p>
            @endif
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         ROW 4 — Retail Sales + Quick Links
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Retail Sales --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Penjualan Retail (Bulan Ini)</p>
            <p class="text-2xl font-bold text-white mt-2">
                Rp {{ number_format($metrics['retail_sales_month'] ?? 0, 0, ',', '.') }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Pomade, shampoo, dll</p>
        </div>

        {{-- Quick Links --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-sm font-semibold text-slate-300 mb-3">Aksi Cepat</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <a href="{{ route('tickets.create') }}"
                    class="flex flex-col items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 hover:border-sky-500 hover:bg-sky-500/10 p-3 text-center transition-colors">
                    <span class="text-xl">🧾</span>
                    <span class="text-xs text-slate-300">Buat Tiket</span>
                </a>
                <a href="{{ route('bookings.create') }}"
                    class="flex flex-col items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 hover:border-sky-500 hover:bg-sky-500/10 p-3 text-center transition-colors">
                    <span class="text-xl">📅</span>
                    <span class="text-xs text-slate-300">Buat Booking</span>
                </a>
                <a href="{{ route('products.index') }}"
                    class="flex flex-col items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 hover:border-sky-500 hover:bg-sky-500/10 p-3 text-center transition-colors">
                    <span class="text-xl">✂️</span>
                    <span class="text-xs text-slate-300">Kelola Layanan</span>
                </a>
                <a href="{{ route('tickets.index') }}"
                    class="flex flex-col items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 hover:border-sky-500 hover:bg-sky-500/10 p-3 text-center transition-colors">
                    <span class="text-xl">📋</span>
                    <span class="text-xs text-slate-300">Semua Tiket</span>
                </a>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
function revenueChart() {
    return {
        async init() {
            try {
                const response = await fetch('{{ route('dashboard.chart-data') }}');
                const { data } = await response.json();

                if (!data || data.length === 0) return;

                const canvas = document.getElementById('chart-canvas');
                const ctx = canvas.getContext('2d');
                const width = canvas.offsetWidth;
                const height = canvas.offsetHeight;
                canvas.width = width;
                canvas.height = height;

                const labels = data.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric' });
                });
                const values = data.map(d => d.revenue);
                const maxVal = Math.max(...values, 1);

                const padLeft = 60;
                const padRight = 16;
                const padTop = 16;
                const padBottom = 32;
                const chartW = width - padLeft - padRight;
                const chartH = height - padTop - padBottom;
                const n = values.length;

                ctx.clearRect(0, 0, width, height);

                // Grid lines
                ctx.strokeStyle = 'rgba(255,255,255,0.05)';
                ctx.lineWidth = 1;
                for (let i = 0; i <= 4; i++) {
                    const y = padTop + chartH - (i / 4) * chartH;
                    ctx.beginPath();
                    ctx.moveTo(padLeft, y);
                    ctx.lineTo(padLeft + chartW, y);
                    ctx.stroke();

                    // Y labels
                    ctx.fillStyle = 'rgba(148,163,184,0.7)';
                    ctx.font = '10px sans-serif';
                    ctx.textAlign = 'right';
                    const labelVal = (maxVal * i / 4);
                    ctx.fillText(labelVal >= 1000000
                        ? (labelVal / 1000000).toFixed(1) + 'jt'
                        : labelVal >= 1000
                        ? (labelVal / 1000).toFixed(0) + 'rb'
                        : labelVal.toFixed(0),
                        padLeft - 6, y + 4);
                }

                // Bars
                const barW = Math.max(4, (chartW / n) * 0.6);
                const gap = chartW / n;

                values.forEach((val, i) => {
                    const x = padLeft + i * gap + (gap - barW) / 2;
                    const barH = (val / maxVal) * chartH;
                    const y = padTop + chartH - barH;

                    // Bar gradient
                    const grad = ctx.createLinearGradient(0, y, 0, y + barH);
                    grad.addColorStop(0, 'rgba(14,165,233,0.9)');
                    grad.addColorStop(1, 'rgba(14,165,233,0.3)');
                    ctx.fillStyle = grad;
                    ctx.beginPath();
                    ctx.roundRect(x, y, barW, barH, 3);
                    ctx.fill();

                    // X labels
                    ctx.fillStyle = 'rgba(148,163,184,0.7)';
                    ctx.font = '10px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(labels[i], x + barW / 2, height - 6);
                });

            } catch (e) {
                // Chart failed silently — not critical
            }
        }
    };
}
</script>
@endpush
