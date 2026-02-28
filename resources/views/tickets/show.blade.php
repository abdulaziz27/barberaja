@extends('layouts.app')

@section('title', 'POS — Tiket #' . substr($ticket->id, 0, 8))

@push('head')
<style>
    /* Ensure full-height POS layout on tablet/desktop */
    @media (min-width: 1024px) {
        .pos-layout { height: calc(100vh - 80px); }
        .pos-product-panel { overflow-y: auto; }
        .pos-cart-panel { overflow-y: auto; }
    }
</style>
@endpush

@section('content')
<div
    x-data="posApp()"
    x-init="init()"
    class="pos-layout flex flex-col lg:flex-row gap-0 -mx-4 -mt-8 lg:h-[calc(100vh-80px)]"
>

    {{-- ═══════════════════════════════════════════════════════════════════════
         LEFT PANEL — Product Grid
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="pos-product-panel flex-1 bg-slate-950 border-r border-slate-800 flex flex-col">

        {{-- Header --}}
        <div class="px-4 pt-4 pb-3 border-b border-slate-800 flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('tickets.index') }}" class="text-xs text-slate-500 hover:text-slate-300">← Tiket</a>
                <h1 class="text-base font-semibold text-white mt-0.5">
                    {{ $ticket->outlet?->name }}
                    <span class="text-slate-500 font-normal">· {{ $ticket->staff?->name }}</span>
                </h1>
            </div>
            <span class="text-xs px-2 py-1 rounded-full font-medium
                @switch($ticket->status)
                    @case('open') bg-sky-500/20 text-sky-400 @break
                    @case('in_progress') bg-amber-500/20 text-amber-400 @break
                    @case('completed') bg-emerald-500/20 text-emerald-400 @break
                    @case('cancelled') bg-rose-500/20 text-rose-400 @break
                    @default bg-slate-700 text-slate-400
                @endswitch
            ">
                @switch($ticket->status)
                    @case('open') Open @break
                    @case('in_progress') Dikerjakan @break
                    @case('completed') Selesai @break
                    @case('cancelled') Dibatalkan @break
                    @default {{ $ticket->status }}
                @endswitch
            </span>
        </div>

        @if($ticket->canAddItems())
        {{-- Type Filter Tabs --}}
        <div class="px-4 pt-3 pb-2 flex gap-2 border-b border-slate-800">
            <button
                @click="activeFilter = 'all'"
                :class="activeFilter === 'all' ? 'bg-sky-500 text-slate-900' : 'bg-slate-800 text-slate-400 hover:text-white'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Semua
            </button>
            @if(isset($products['service']) && $products['service']->isNotEmpty())
            <button
                @click="activeFilter = 'service'"
                :class="activeFilter === 'service' ? 'bg-sky-500 text-slate-900' : 'bg-slate-800 text-slate-400 hover:text-white'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Layanan
            </button>
            @endif
            @if(isset($products['bundle']) && $products['bundle']->isNotEmpty())
            <button
                @click="activeFilter = 'bundle'"
                :class="activeFilter === 'bundle' ? 'bg-sky-500 text-slate-900' : 'bg-slate-800 text-slate-400 hover:text-white'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Paket
            </button>
            @endif
            @if(isset($products['retail']) && $products['retail']->isNotEmpty())
            <button
                @click="activeFilter = 'retail'"
                :class="activeFilter === 'retail' ? 'bg-sky-500 text-slate-900' : 'bg-slate-800 text-slate-400 hover:text-white'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Retail
            </button>
            @endif
        </div>

        {{-- Product Grid --}}
        <div class="pos-product-panel flex-1 overflow-y-auto p-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-3">

                @foreach(['service', 'bundle', 'retail'] as $type)
                    @if(isset($products[$type]))
                        @foreach($products[$type] as $product)
                        <button
                            type="button"
                            x-show="activeFilter === 'all' || activeFilter === '{{ $type }}'"
                            @click="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', {{ (float) $product->price }}, '{{ $type }}')"
                            class="text-left rounded-xl border border-slate-800 bg-slate-900 hover:border-sky-500 hover:bg-sky-500/5 p-3 transition-colors group">
                            <div class="flex items-start justify-between gap-1 mb-2">
                                <span class="text-xs px-1.5 py-0.5 rounded font-medium
                                    @if($type === 'service') bg-sky-500/20 text-sky-400
                                    @elseif($type === 'bundle') bg-purple-500/20 text-purple-400
                                    @else bg-amber-500/20 text-amber-400
                                    @endif">
                                    @if($type === 'service') Layanan
                                    @elseif($type === 'bundle') Paket
                                    @else Retail
                                    @endif
                                </span>
                                @if($type === 'retail' && $product->stock_qty !== null)
                                    <span class="text-xs text-slate-500">Stok: {{ $product->stock_qty }}</span>
                                @endif
                            </div>
                            <p class="font-medium text-white text-sm leading-tight group-hover:text-sky-300">{{ $product->name }}</p>
                            @if($type !== 'retail' && $product->duration_minutes)
                                <p class="text-xs text-slate-500 mt-0.5">{{ $product->duration_minutes }} menit</p>
                            @endif
                            <p class="text-sky-400 font-semibold text-sm mt-2">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                        </button>
                        @endforeach
                    @endif
                @endforeach

            </div>
        </div>
        @else
        {{-- Ticket not editable --}}
        <div class="flex-1 flex items-center justify-center text-slate-500 text-sm p-8 text-center">
            <div>
                <p class="text-2xl mb-2">
                    @if($ticket->isCompleted()) ✅ @elseif($ticket->isCancelled()) ❌ @else 🔒 @endif
                </p>
                <p>Tiket ini tidak dapat diubah.</p>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         RIGHT PANEL — Cart / Ticket Summary
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="pos-cart-panel w-full lg:w-80 xl:w-96 bg-slate-900 flex flex-col border-t lg:border-t-0 border-slate-800">

        {{-- Cart Header --}}
        <div class="px-4 py-3 border-b border-slate-800">
            <h2 class="text-sm font-semibold text-slate-300">Keranjang</h2>
        </div>

        {{-- Existing items (from DB) --}}
        <div class="flex-1 overflow-y-auto">
            @if($ticket->items->isNotEmpty())
            <div class="divide-y divide-slate-800">
                @foreach($ticket->items as $item)
                <div class="px-4 py-3 flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $item->product?->name }}</p>
                        <p class="text-xs text-slate-500">Rp {{ number_format($item->price, 0, ',', '.') }} × {{ $item->qty }}</p>
                    </div>
                    <span class="text-sm font-semibold text-white whitespace-nowrap">
                        Rp {{ number_format($item->total, 0, ',', '.') }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Pending cart items (Alpine, not yet submitted) --}}
            @if($ticket->canAddItems())
            <template x-if="cart.length > 0">
                <div>
                    <div class="px-4 py-2 bg-sky-500/10 border-y border-sky-500/20">
                        <p class="text-xs text-sky-400 font-medium">Belum disimpan</p>
                    </div>
                    <div class="divide-y divide-slate-800">
                        <template x-for="(item, index) in cart" :key="item.id + '_' + index">
                            <div class="px-4 py-3 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white truncate" x-text="item.name"></p>
                                    <p class="text-xs text-slate-500" x-text="'Rp ' + formatPrice(item.price) + ' × ' + item.qty"></p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <template x-if="item.type === 'retail'">
                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="decreaseQty(index)"
                                                class="w-6 h-6 rounded bg-slate-700 hover:bg-slate-600 text-white text-xs flex items-center justify-center">−</button>
                                            <span class="text-sm text-white w-5 text-center" x-text="item.qty"></span>
                                            <button type="button" @click="increaseQty(index)"
                                                class="w-6 h-6 rounded bg-slate-700 hover:bg-slate-600 text-white text-xs flex items-center justify-center">+</button>
                                        </div>
                                    </template>
                                    <span class="text-sm font-semibold text-white whitespace-nowrap ml-1" x-text="'Rp ' + formatPrice(item.price * item.qty)"></span>
                                    <button type="button" @click="removeFromCart(index)"
                                        class="ml-1 text-slate-600 hover:text-rose-400 text-xs">✕</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            @endif

            @if($ticket->items->isEmpty())
            <template x-if="cart.length === 0">
                <div class="px-4 py-8 text-center text-slate-500 text-sm">
                    <p class="text-2xl mb-2">🛒</p>
                    <p>Tap produk untuk menambahkan</p>
                </div>
            </template>
            @endif
        </div>

        {{-- Totals --}}
        <div class="border-t border-slate-800 px-4 py-3 space-y-1">
            <div class="flex justify-between text-sm text-slate-400">
                <span>Subtotal tersimpan</span>
                <span>Rp {{ number_format($ticket->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($ticket->canAddItems())
            <div class="flex justify-between text-sm text-slate-400" x-show="cart.length > 0">
                <span>Pending</span>
                <span x-text="'Rp ' + formatPrice(cartTotal)"></span>
            </div>
            @endif
            @if($ticket->dp_amount > 0)
            <div class="flex justify-between text-sm text-slate-400">
                <span>DP</span>
                <span class="text-emerald-400">− Rp {{ number_format($ticket->dp_amount, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between text-base font-bold text-white pt-1 border-t border-slate-700">
                <span>Total</span>
                <span>Rp {{ number_format($ticket->total, 0, ',', '.') }}</span>
            </div>
            @if($ticket->staff_commission !== null)
            <div class="flex justify-between text-xs text-slate-500">
                <span>Komisi staff</span>
                <span>Rp {{ number_format($ticket->staff_commission, 0, ',', '.') }}</span>
            </div>
            @endif
        </div>

        {{-- Action Buttons --}}
        <div class="border-t border-slate-800 p-4 space-y-2">

            {{-- Save pending items to ticket --}}
            @if($ticket->canAddItems())
            <form
                method="POST"
                id="form-add-items"
                action="{{ route('tickets.addItem', $ticket) }}"
                x-ref="addItemsForm"
                @submit.prevent="submitCartItems"
            >
                @csrf
                <input type="hidden" name="product_id" x-ref="productIdInput" value="">
                <input type="hidden" name="qty" x-ref="qtyInput" value="1">
            </form>

            <button
                type="button"
                @click="submitAllCartItems"
                :disabled="cart.length === 0 || submitting"
                :class="cart.length === 0 || submitting ? 'opacity-40 cursor-not-allowed' : 'hover:bg-sky-400'"
                class="w-full py-2.5 rounded-xl bg-sky-500 text-slate-900 font-semibold text-sm transition-colors">
                <span x-show="!submitting">Simpan Item (<span x-text="cart.length"></span>)</span>
                <span x-show="submitting">Menyimpan...</span>
            </button>
            @endif

            {{-- Start ticket --}}
            @if($ticket->isOpen())
            <form method="POST" action="{{ route('tickets.start', $ticket) }}">
                @csrf
                <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold text-sm">
                    Mulai Kerjakan
                </button>
            </form>
            @endif

            {{-- Complete ticket — sticky, double-submit prevention --}}
            @if($ticket->canComplete())
            <form
                method="POST"
                action="{{ route('tickets.complete', $ticket) }}"
                x-data="{ completing: false }"
                @submit.prevent="
                    if (completing) return;
                    if (!confirm('Selesaikan tiket dan hitung komisi?')) return;
                    completing = true;
                    $el.submit();
                "
            >
                @csrf
                <button
                    type="submit"
                    :disabled="completing"
                    :class="completing ? 'opacity-60 cursor-not-allowed' : 'hover:bg-emerald-400'"
                    class="w-full py-3 rounded-xl bg-emerald-500 text-slate-900 font-bold text-base transition-colors">
                    <span x-show="!completing">✅ Selesaikan Tiket</span>
                    <span x-show="completing">Memproses...</span>
                </button>
            </form>
            @endif

            {{-- Cancel ticket --}}
            @if(!$ticket->isCompleted() && !$ticket->isCancelled() && auth()->user()->can('cancel', $ticket))
            <form method="POST" action="{{ route('tickets.cancel', $ticket) }}" onsubmit="return confirm('Batalkan tiket ini?');">
                @csrf
                <button type="submit" class="w-full py-2 rounded-xl border border-rose-800 text-rose-400 hover:bg-rose-900/30 text-sm">
                    Batalkan Tiket
                </button>
            </form>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
function posApp() {
    return {
        activeFilter: 'all',
        cart: [],
        submitting: false,

        init() {
            // Nothing to init from server state
        },

        get cartTotal() {
            return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        },

        addToCart(productId, name, price, type) {
            // For retail: increase qty if already in cart
            if (type === 'retail') {
                const existing = this.cart.find(i => i.id === productId);
                if (existing) {
                    existing.qty++;
                    return;
                }
            }
            // For service/bundle: always add new line (can have multiple)
            this.cart.push({ id: productId, name, price, qty: 1, type });
        },

        increaseQty(index) {
            this.cart[index].qty++;
        },

        decreaseQty(index) {
            if (this.cart[index].qty > 1) {
                this.cart[index].qty--;
            } else {
                this.removeFromCart(index);
            }
        },

        removeFromCart(index) {
            this.cart.splice(index, 1);
        },

        formatPrice(amount) {
            return new Intl.NumberFormat('id-ID').format(Math.round(amount));
        },

        /**
         * Submit all cart items one by one via sequential form POSTs.
         * Each item is submitted as a separate addItem request.
         */
        async submitAllCartItems() {
            if (this.cart.length === 0 || this.submitting) return;
            this.submitting = true;

            try {
                for (const item of this.cart) {
                    await this.submitSingleItem(item.id, item.qty);
                }
                // Reload page to reflect saved items
                window.location.reload();
            } catch (e) {
                this.submitting = false;
                alert('Gagal menyimpan item. Silakan coba lagi.');
            }
        },

        submitSingleItem(productId, qty) {
            return new Promise((resolve, reject) => {
                const form = document.getElementById('form-add-items');
                const csrfToken = form.querySelector('[name="_token"]').value;

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new URLSearchParams({
                        _token: csrfToken,
                        product_id: productId,
                        qty: qty,
                    }),
                    redirect: 'follow',
                }).then(response => {
                    if (response.ok || response.redirected) {
                        resolve();
                    } else {
                        reject(new Error('HTTP ' + response.status));
                    }
                }).catch(reject);
            });
        },
    };
}
</script>
@endpush
@endsection
