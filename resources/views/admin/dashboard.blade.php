@extends('layouts.app')

@section('title', 'Admin — Monitoring')

@section('content')
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-white">← Dashboard</a>
        <h1 class="text-2xl font-semibold mt-1">Admin — Monitoring</h1>
        <p class="text-sm text-slate-500 mt-1">Escrow dari ledger & audit log sensitif.</p>
    </div>

    <section class="mb-8">
        <h2 class="text-lg font-medium text-slate-300 mb-3">Total Escrow (owner_escrow)</h2>
        <p class="text-2xl font-semibold text-emerald-400">Rp {{ number_format($totalEscrow, 0, ',', '.') }}</p>
        <p class="text-xs text-slate-500 mt-1">Jalankan <code class="bg-slate-800 px-1 rounded">php artisan escrow:reconcile</code> untuk laporan per tenant.</p>
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-medium text-slate-300 mb-3">Tenants & Balance</h2>
        <div class="rounded-xl border border-slate-800 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50 text-slate-400 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Plan</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Escrow (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($tenants as $t)
                        <tr>
                            <td class="px-4 py-2">{{ $t->name }}</td>
                            <td class="px-4 py-2">{{ $t->plan_type }}</td>
                            <td class="px-4 py-2">{{ $t->plan_status }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($balances[$t->id] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-slate-500">Belum ada tenant.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="text-lg font-medium text-slate-300 mb-3">Audit Log (50 terakhir)</h2>
        <div class="rounded-xl border border-slate-800 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50 text-slate-400 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Waktu</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                        <th class="px-4 py-3 font-medium">Tenant</th>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Subject</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($recentLogs as $log)
                        <tr>
                            <td class="px-4 py-2 text-slate-500">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2">{{ $log->action }}</td>
                            <td class="px-4 py-2">{{ $log->tenant?->name ?? '–' }}</td>
                            <td class="px-4 py-2">{{ $log->user?->name ?? 'system' }}</td>
                            <td class="px-4 py-2">{{ $log->subject_type ? class_basename($log->subject_type).' '.$log->subject_id : '–' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-slate-500">Belum ada audit log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
