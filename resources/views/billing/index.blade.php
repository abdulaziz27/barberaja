@extends('layouts.app')

@section('title', 'Billing & Plan')

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl font-bold text-white">Billing & Plan</h1>
        <p class="text-sm text-slate-500 mt-0.5">Kelola plan dan lihat status tagihan kamu.</p>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         Current Plan Status
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl border {{ $tenant->isSubscriptionOverdue() ? 'border-rose-700 bg-rose-900/20' : 'border-slate-800 bg-slate-900' }} p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide font-medium">Plan Saat Ini</p>
                <p class="text-3xl font-bold text-white mt-1 capitalize">{{ $tenant->plan_type }}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full font-medium
                        {{ $tenant->isSubscriptionOverdue() ? 'bg-rose-500/20 text-rose-400' : 'bg-emerald-500/20 text-emerald-400' }}">
                        {{ $tenant->isSubscriptionOverdue() ? '⚠️ Overdue' : '✅ Aktif' }}
                    </span>
                    @if($tenant->plan_expires_at)
                        <span class="text-xs text-slate-500">Berlaku hingga {{ $tenant->plan_expires_at->format('d M Y') }}</span>
                    @endif
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-500">Saldo Escrow</p>
                <p class="text-xl font-bold text-sky-400 mt-1">Rp {{ number_format($escrowBalance, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Tersedia untuk withdrawal</p>
            </div>
        </div>

        @if($tenant->isSubscriptionOverdue())
        <div class="mt-4 rounded-xl bg-rose-900/30 border border-rose-800 text-rose-300 text-sm px-4 py-3">
            ⚠️ Subscription kamu overdue. Pastikan saldo escrow mencukupi untuk pemotongan bulanan (Rp {{ number_format(\App\Models\Tenant::DEFAULT_SUBSCRIPTION_PRICE_PRO, 0, ',', '.') }}).
        </div>
        @endif

        @if($tenant->isFree())
        <div class="mt-4 rounded-xl bg-amber-900/20 border border-amber-800/50 text-amber-300 text-sm px-4 py-3">
            💡 Kamu menggunakan plan Free. Setiap tiket selesai dikenakan fee Rp {{ number_format(\App\Models\Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE, 0, ',', '.') }}.
            Estimasi 30 transaksi/bulan = <strong>Rp {{ number_format($feeSimulation, 0, ',', '.') }}</strong>.
            Upgrade ke Pro untuk menghilangkan fee ini.
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         Plan Comparison
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div>
        <h2 class="text-sm font-semibold text-slate-300 mb-3">Perbandingan Plan</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($planComparison as $planKey => $plan)
            <div class="rounded-2xl border p-5 relative
                {{ $tenant->plan_type === $planKey
                    ? 'border-sky-500 bg-sky-500/5 ring-1 ring-sky-500'
                    : 'border-slate-800 bg-slate-900' }}">

                @if($tenant->plan_type === $planKey)
                    <span class="absolute top-3 right-3 text-xs bg-sky-500 text-slate-900 font-bold px-2 py-0.5 rounded-full">
                        Aktif
                    </span>
                @endif

                <h3 class="text-lg font-bold text-white">{{ $plan['name'] }}</h3>

                <div class="mt-2 mb-4">
                    @if($plan['subscription'] > 0)
                        <p class="text-2xl font-bold text-white">
                            Rp {{ number_format($plan['subscription'], 0, ',', '.') }}
                            <span class="text-sm font-normal text-slate-400">/bulan</span>
                        </p>
                        <p class="text-xs text-emerald-400 mt-0.5">Tidak ada fee per transaksi</p>
                    @else
                        <p class="text-2xl font-bold text-white">Gratis</p>
                        <p class="text-xs text-amber-400 mt-0.5">+ Rp {{ number_format($plan['fee_per_transaction'], 0, ',', '.') }} per tiket selesai</p>
                    @endif
                </div>

                <ul class="space-y-1.5 mb-5">
                    @foreach($plan['features'] as $feature)
                    <li class="flex items-center gap-2 text-sm text-slate-300">
                        <span class="text-emerald-400 shrink-0">✓</span>
                        {{ $feature }}
                    </li>
                    @endforeach
                    @foreach($plan['limitations'] as $limitation)
                    <li class="flex items-center gap-2 text-sm text-slate-500">
                        <span class="text-rose-400 shrink-0">✗</span>
                        {{ $limitation }}
                    </li>
                    @endforeach
                </ul>

                @if($tenant->plan_type !== $planKey)
                <form method="POST" action="{{ route('billing.upgrade') }}">
                    @csrf
                    <input type="hidden" name="plan_type" value="{{ $planKey }}">
                    <button type="submit"
                        onclick="return confirm('Ubah plan ke {{ $plan['name'] }}?')"
                        class="w-full py-2.5 rounded-xl font-semibold text-sm transition-colors
                            {{ $planKey === \App\Models\Tenant::PLAN_TYPE_PRO
                                ? 'bg-sky-500 hover:bg-sky-400 text-slate-900'
                                : 'border border-slate-700 text-slate-300 hover:bg-slate-800' }}">
                        {{ $planKey === \App\Models\Tenant::PLAN_TYPE_PRO ? '⬆️ Upgrade ke Pro' : 'Downgrade ke Free' }}
                    </button>
                </form>
                @else
                <div class="w-full py-2.5 rounded-xl text-center text-sm text-slate-500 border border-slate-800">
                    Plan Aktif
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         Fee Simulation (Free Plan Only)
    ═══════════════════════════════════════════════════════════════════════ --}}
    @if($tenant->isFree())
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-sm font-semibold text-slate-300 mb-4">Simulasi Biaya</h2>
        <p class="text-xs text-slate-500 mb-4">Berapa yang kamu bayar vs Pro plan berdasarkan jumlah transaksi per bulan.</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 text-xs border-b border-slate-800">
                        <th class="pb-2 font-medium">Transaksi/bulan</th>
                        <th class="pb-2 font-medium text-right">Free (fee)</th>
                        <th class="pb-2 font-medium text-right">Pro (subscription)</th>
                        <th class="pb-2 font-medium text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach([10, 20, 30, 50, 100] as $txCount)
                    @php
                        $freeCost = $txCount * \App\Models\Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE;
                        $proCost = \App\Models\Tenant::DEFAULT_SUBSCRIPTION_PRICE_PRO;
                        $diff = $freeCost - $proCost;
                    @endphp
                    <tr class="{{ $diff > 0 ? 'text-amber-300' : 'text-slate-300' }}">
                        <td class="py-2">{{ $txCount }}×</td>
                        <td class="py-2 text-right">Rp {{ number_format($freeCost, 0, ',', '.') }}</td>
                        <td class="py-2 text-right text-slate-400">Rp {{ number_format($proCost, 0, ',', '.') }}</td>
                        <td class="py-2 text-right {{ $diff > 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                            {{ $diff > 0 ? '+' : '' }}Rp {{ number_format(abs($diff), 0, ',', '.') }}
                            {{ $diff > 0 ? '(lebih mahal)' : '(lebih hemat)' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-500 mt-3">
            💡 Jika kamu memiliki lebih dari {{ ceil(\App\Models\Tenant::DEFAULT_SUBSCRIPTION_PRICE_PRO / \App\Models\Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE) }} transaksi/bulan, Pro plan lebih hemat.
        </p>
    </div>
    @endif

</div>
@endsection
