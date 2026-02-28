<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $outlet->name }} — Booking Online</title>
    <meta name="description" content="Booking online di {{ $outlet->name }}. Pilih layanan, kapster, dan jadwal favoritmu.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">

    {{-- Header --}}
    <header class="sticky top-0 z-20 bg-slate-950/95 backdrop-blur border-b border-slate-800">
        <div class="max-w-lg mx-auto px-4 py-3 flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-bold text-white truncate">{{ $outlet->name }}</h1>
                @if($outlet->address)
                    <p class="text-xs text-slate-500 truncate">{{ $outlet->address }}</p>
                @endif
            </div>
            <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-slate-300 shrink-0">Login</a>
        </div>
    </header>

    <main
        x-data="bookingApp('{{ $outlet->slug }}', {{ $outlet->require_dp ? 'true' : 'false' }})"
        class="max-w-lg mx-auto px-4 pb-32"
    >

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mt-4 rounded-xl bg-emerald-900/40 border border-emerald-700 text-emerald-200 text-sm px-4 py-3">
                ✅ {{ session('success') }}
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             STEP 1 — Pilih Layanan
        ═══════════════════════════════════════════════════════════════════ --}}
        <section class="mt-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-sky-500 text-slate-900 text-xs font-bold flex items-center justify-center">1</span>
                <h2 class="text-sm font-semibold text-slate-300">Pilih Layanan</h2>
            </div>

            @if($services->isNotEmpty())
            <div class="grid grid-cols-1 gap-2">
                @foreach($services as $service)
                <button
                    type="button"
                    @click="selectService('{{ $service->id }}', '{{ addslashes($service->name) }}', {{ (float) $service->price }}, {{ (int) $service->duration_minutes }})"
                    :class="selectedService?.id === '{{ $service->id }}'
                        ? 'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500'
                        : 'border-slate-800 bg-slate-900 hover:border-slate-600'"
                    class="w-full text-left rounded-xl border px-4 py-3 transition-all flex items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-white text-sm">{{ $service->name }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $service->duration_minutes }} menit</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sky-400 font-semibold text-sm">Rp {{ number_format($service->price, 0, ',', '.') }}</p>
                        @if($outlet->require_dp)
                            <p class="text-xs text-slate-500">+ DP</p>
                        @endif
                    </div>
                </button>
                @endforeach
            </div>
            @else
            <p class="text-slate-500 text-sm">Belum ada layanan tersedia.</p>
            @endif
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             STEP 2 — Pilih Kapster (opsional)
        ═══════════════════════════════════════════════════════════════════ --}}
        <section class="mt-6" x-show="selectedService" x-transition>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-sky-500 text-slate-900 text-xs font-bold flex items-center justify-center">2</span>
                <h2 class="text-sm font-semibold text-slate-300">Pilih Kapster <span class="text-slate-500 font-normal">(opsional)</span></h2>
            </div>

            <div class="grid grid-cols-2 gap-2">
                {{-- Any staff option --}}
                <button
                    type="button"
                    @click="selectStaff(null, 'Kapster Tersedia')"
                    :class="selectedStaff === null ? 'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500' : 'border-slate-800 bg-slate-900 hover:border-slate-600'"
                    class="rounded-xl border px-3 py-3 text-sm font-medium text-white transition-all">
                    ✨ Siapapun
                </button>

                @foreach($staffUsers as $staff)
                <button
                    type="button"
                    @click="selectStaff('{{ $staff->id }}', '{{ addslashes($staff->name) }}')"
                    :class="selectedStaff?.id === '{{ $staff->id }}' ? 'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500' : 'border-slate-800 bg-slate-900 hover:border-slate-600'"
                    class="rounded-xl border px-3 py-3 text-sm font-medium text-white transition-all text-left">
                    <p class="truncate">{{ $staff->name }}</p>
                </button>
                @endforeach
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             STEP 3 — Pilih Tanggal
        ═══════════════════════════════════════════════════════════════════ --}}
        <section class="mt-6" x-show="staffSelected" x-transition>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-sky-500 text-slate-900 text-xs font-bold flex items-center justify-center">3</span>
                <h2 class="text-sm font-semibold text-slate-300">Pilih Tanggal</h2>
            </div>

            {{-- Date picker: next 14 days --}}
            <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                @for($i = 0; $i < 14; $i++)
                    @php
                        $d = now()->addDays($i);
                    @endphp
                    <button
                        type="button"
                        @click="selectDate('{{ $d->format('Y-m-d') }}')"
                        :class="selectedDate === '{{ $d->format('Y-m-d') }}' ? 'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500' : 'border-slate-800 bg-slate-900 hover:border-slate-600'"
                        class="shrink-0 rounded-xl border px-3 py-2 text-center transition-all min-w-[56px]">
                        <p class="text-xs text-slate-400">{{ $d->isoFormat('ddd') }}</p>
                        <p class="text-sm font-bold text-white">{{ $d->format('d') }}</p>
                        <p class="text-xs text-slate-500">{{ $d->isoFormat('MMM') }}</p>
                    </button>
                @endfor
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             STEP 4 — Pilih Jam
        ═══════════════════════════════════════════════════════════════════ --}}
        <section class="mt-6" x-show="selectedDate" x-transition>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-sky-500 text-slate-900 text-xs font-bold flex items-center justify-center">4</span>
                <h2 class="text-sm font-semibold text-slate-300">Pilih Jam</h2>
            </div>

            {{-- Loading state --}}
            <div x-show="loadingSlots" class="text-center py-6 text-slate-500 text-sm">
                <div class="inline-block w-5 h-5 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mb-2"></div>
                <p>Memuat slot...</p>
            </div>

            {{-- Slot grid --}}
            <div x-show="!loadingSlots && slots.length > 0" class="grid grid-cols-4 gap-2">
                <template x-for="slot in slots" :key="slot.datetime">
                    <button
                        type="button"
                        :disabled="!slot.available"
                        @click="slot.available && selectSlot(slot.datetime, slot.time)"
                        :class="{
                            'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500 text-white': selectedSlot === slot.datetime,
                            'border-slate-800 bg-slate-900 hover:border-slate-600 text-white': slot.available && selectedSlot !== slot.datetime,
                            'border-slate-800/50 bg-slate-900/50 text-slate-600 cursor-not-allowed line-through': !slot.available,
                        }"
                        class="rounded-xl border py-2.5 text-sm font-medium transition-all"
                        x-text="slot.time">
                    </button>
                </template>
            </div>

            <div x-show="!loadingSlots && slots.length === 0 && selectedDate" class="text-center py-6 text-slate-500 text-sm">
                <p>Tidak ada slot tersedia untuk tanggal ini.</p>
                <p class="text-xs mt-1">Coba tanggal lain.</p>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             STEP 5 — Data Diri
        ═══════════════════════════════════════════════════════════════════ --}}
        <section class="mt-6" x-show="selectedSlot" x-transition>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-sky-500 text-slate-900 text-xs font-bold flex items-center justify-center">5</span>
                <h2 class="text-sm font-semibold text-slate-300">Data Diri</h2>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nama</label>
                    <input
                        type="text"
                        x-model="customerName"
                        placeholder="Nama kamu"
                        autocomplete="name"
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 text-slate-100 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">No. HP <span class="text-slate-500">(opsional)</span></label>
                    <input
                        type="tel"
                        x-model="customerPhone"
                        placeholder="08xxxxxxxxxx"
                        autocomplete="tel"
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 text-slate-100 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
            </div>
        </section>

        {{-- Error message --}}
        <div x-show="errorMessage" x-transition class="mt-4 rounded-xl bg-rose-900/40 border border-rose-700 text-rose-200 text-sm px-4 py-3" x-text="errorMessage"></div>

    </main>

    {{-- ═══════════════════════════════════════════════════════════════════════
         STICKY BOTTOM — Booking Summary + CTA
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div
        x-data
        class="fixed bottom-0 left-0 right-0 z-30 bg-slate-900/95 backdrop-blur border-t border-slate-800 px-4 py-4"
        x-show="$store.booking.selectedService"
    >
        <div class="max-w-lg mx-auto">
            {{-- Summary --}}
            <div class="flex items-center justify-between mb-3 text-sm" x-show="$store.booking.selectedService">
                <div class="min-w-0">
                    <p class="font-medium text-white truncate" x-text="$store.booking.selectedService?.name || ''"></p>
                    <p class="text-xs text-slate-400" x-text="
                        ($store.booking.selectedStaff?.name || 'Kapster tersedia') +
                        ($store.booking.selectedSlotTime ? ' · ' + $store.booking.selectedSlotTime : '')
                    "></p>
                </div>
                <p class="text-sky-400 font-bold shrink-0 ml-3" x-text="'Rp ' + formatPrice($store.booking.selectedService?.price || 0)"></p>
            </div>

            {{-- CTA Button --}}
            <form
                method="POST"
                :action="'/' + '{{ $outlet->slug }}' + '/book'"
                @submit.prevent="submitBooking"
                id="booking-form"
            >
                @csrf
                <input type="hidden" name="product_id" :value="$store.booking.selectedService?.id">
                <input type="hidden" name="staff_id" :value="$store.booking.selectedStaff?.id || ''">
                <input type="hidden" name="start_time" :value="$store.booking.selectedSlot">
                <input type="hidden" name="customer_name" :value="$store.booking.customerName">
                <input type="hidden" name="customer_phone" :value="$store.booking.customerPhone">

                <button
                    type="submit"
                    :disabled="!canSubmit || submitting"
                    :class="canSubmit && !submitting ? 'bg-sky-500 hover:bg-sky-400 text-slate-900' : 'bg-slate-700 text-slate-500 cursor-not-allowed'"
                    class="w-full py-3.5 rounded-xl font-bold text-base transition-colors">
                    <span x-show="!submitting">
                        <span x-show="!$store.booking.selectedSlot">Pilih layanan & jadwal</span>
                        <span x-show="$store.booking.selectedSlot && !$store.booking.customerName">Isi nama kamu</span>
                        <span x-show="$store.booking.selectedSlot && $store.booking.customerName">
                            {{ $outlet->require_dp ? '💳 Lanjut ke Pembayaran DP' : '✅ Booking Sekarang' }}
                        </span>
                    </span>
                    <span x-show="submitting">Memproses...</span>
                </button>
            </form>
        </div>
    </div>

    <script>
    // Alpine store for shared state between main app and sticky footer
    document.addEventListener('alpine:init', () => {
        Alpine.store('booking', {
            selectedService: null,
            selectedStaff: null,
            selectedSlot: null,
            selectedSlotTime: null,
            customerName: '',
            customerPhone: '',
        });
    });

    function bookingApp(slug, requireDp) {
        return {
            slug,
            requireDp,

            // Local state
            selectedService: null,
            selectedStaff: null,
            staffSelected: false,
            selectedDate: null,
            selectedSlot: null,
            selectedSlotTime: null,
            customerName: '',
            customerPhone: '',
            slots: [],
            loadingSlots: false,
            submitting: false,
            errorMessage: '',

            get canSubmit() {
                return this.selectedService && this.selectedSlot && this.customerName.trim().length > 0;
            },

            selectService(id, name, price, duration) {
                this.selectedService = { id, name, price, duration };
                this.selectedStaff = null;
                this.staffSelected = false;
                this.selectedDate = null;
                this.selectedSlot = null;
                this.selectedSlotTime = null;
                this.slots = [];
                this.errorMessage = '';
                Alpine.store('booking').selectedService = { id, name, price };
                Alpine.store('booking').selectedSlot = null;
                Alpine.store('booking').selectedSlotTime = null;
            },

            selectStaff(id, name) {
                this.selectedStaff = id ? { id, name } : null;
                this.staffSelected = true;
                this.selectedDate = null;
                this.selectedSlot = null;
                this.selectedSlotTime = null;
                this.slots = [];
                Alpine.store('booking').selectedStaff = id ? { id, name } : null;
                Alpine.store('booking').selectedSlot = null;
            },

            selectDate(date) {
                this.selectedDate = date;
                this.selectedSlot = null;
                this.selectedSlotTime = null;
                Alpine.store('booking').selectedSlot = null;
                this.fetchSlots();
            },

            selectSlot(datetime, time) {
                this.selectedSlot = datetime;
                this.selectedSlotTime = time;
                Alpine.store('booking').selectedSlot = datetime;
                Alpine.store('booking').selectedSlotTime = time;
            },

            async fetchSlots() {
                if (!this.selectedService || !this.selectedDate) return;

                this.loadingSlots = true;
                this.slots = [];
                this.errorMessage = '';

                try {
                    const params = new URLSearchParams({
                        product_id: this.selectedService.id,
                        date: this.selectedDate,
                    });
                    if (this.selectedStaff) {
                        params.set('staff_id', this.selectedStaff.id);
                    }

                    const response = await fetch(`/${this.slug}/slots?${params}`);
                    const data = await response.json();

                    if (data.slots) {
                        this.slots = data.slots;
                    } else if (data.staff_slots) {
                        // Merge all staff slots, mark available if ANY staff is free
                        const merged = {};
                        for (const staffData of Object.values(data.staff_slots)) {
                            for (const slot of staffData.slots) {
                                if (!merged[slot.datetime]) {
                                    merged[slot.datetime] = { ...slot };
                                } else if (slot.available) {
                                    merged[slot.datetime].available = true;
                                }
                            }
                        }
                        this.slots = Object.values(merged).sort((a, b) => a.datetime.localeCompare(b.datetime));
                    }
                } catch (e) {
                    this.errorMessage = 'Gagal memuat slot. Silakan coba lagi.';
                } finally {
                    this.loadingSlots = false;
                }
            },

            async submitBooking() {
                if (!this.canSubmit || this.submitting) return;

                this.submitting = true;
                this.errorMessage = '';

                // Sync Alpine store to form
                Alpine.store('booking').customerName = this.customerName;
                Alpine.store('booking').customerPhone = this.customerPhone;

                // Submit the form
                document.getElementById('booking-form').submit();
            },

            formatPrice(amount) {
                return new Intl.NumberFormat('id-ID').format(Math.round(amount));
            },
        };
    }
    </script>

    <footer class="mt-16 pb-32 text-center text-xs text-slate-700">
        Powered by BarberAja
    </footer>

</body>
</html>
