<?php
    use Filament\Notifications\Notification;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Filament\Forms\Components\DatePicker;
    use Filament\Forms\Components\DateTimePicker;
    use Filament\Forms\Components\TimePicker;
    use Livewire\Attributes\Session;
    use Carbon\Carbon;
    use App\Models\PerformaToko;

    middleware('auth');
    name('datacenter-organik-iklan.step1');

    new class extends Component
    {
        public int $bulan = 0;
        public int $tahun;
        public array $availableYears = [2024, 2025]; // Daftar tahun yang tersedia
        public bool $isSubmitDisabled = true;

        public function mount(): void
        {
            $this->tahun = now()->year;
            $this->bulan = 0;
            $this->updateSubmitStatus();
        }

        public function onBulanChange(): void
        {
            $this->validateBulan();
            $this->updateSubmitStatus();
        }

        // public function updatedBulan($value): void
        // {
        //     $this->validateOnly('bulan', [
        //         'bulan' => [
        //             'required',
        //             'not_in:0',
        //             function ($attribute, $value, $fail) {
        //                 if (PerformaToko::where('user_id', auth()->id())
        //                     ->where('tahun', $this->tahun)
        //                     ->where('bulan', $value)
        //                     ->exists()) {
        //                     $fail('Data untuk periode ini sudah ada.');
        //                 }
        //             }
        //         ]
        //     ]);
            
        //     $this->isSubmitDisabled = $this->bulan == 0 || $this->getErrorBag()->has('bulan');
        // }

        public function updatedTahun(): void
        {
            $this->bulan = 0; // Reset bulan saat tahun berubah
            $this->updateSubmitStatus();
        }

        private function validateBulan(): void
        {
            $this->validateOnly('bulan', [
                'bulan' => [
                    'required',
                    'not_in:0',
                    function ($attribute, $value, $fail) {
                        $exists = PerformaToko::where('user_id', auth()->id())
                            ->where('tahun', $this->tahun)
                            ->where('bulan', $value)
                            ->exists();

                        if ($exists) {
                            $fail('Data untuk periode ini sudah ada.');
                        }
                    }
                ]
            ]);
        }

        public function updateSubmitStatus(): void
        {
            $this->isSubmitDisabled = $this->bulan == 0 || $this->getErrorBag()->has('bulan');
        }

        public function getAvailableMonths(): array
        {
            $currentYear = Carbon::now()->year;
            $currentMonth = Carbon::now()->month;

            // Generate semua bulan dalam setahun
            $allMonths = [];
            for ($i = 1; $i <= 12; $i++) {
                $allMonths[$i] = Carbon::createFromDate(null, $i, 1)->translatedFormat('F');
            }

            // Filter bulan yang valid berdasarkan tahun
            if ($this->tahun == $currentYear) {
                $allMonths = array_slice($allMonths, 0, $currentMonth - 1, true);
            }

            // Ambil existing months dengan single query
            $existingMonths = cache()->remember(
                key: 'user_months:'.auth()->id().':'.$this->tahun,
                ttl: 300, // 5 menit cache
                callback: fn() => PerformaToko::where('user_id', auth()->id())
                    ->where('tahun', $this->tahun)
                    ->pluck('bulan')
                    ->toArray()
            );

            return array_diff_key($allMonths, array_flip($existingMonths));
        }

        public function step2(): mixed
        {
            $validated = $this->validate([
                'bulan' => [
                    'required',
                    'not_in:0',
                    function ($attribute, $value, $fail) {
                        $exists = PerformaToko::where('user_id', auth()->id())
                            ->where('tahun', $this->tahun)
                            ->where('bulan', $value)
                            ->exists();

                        if ($exists) {
                            $fail('Data untuk periode ini sudah ada.');
                        }
                    }
                ],
                'tahun' => ['required']
            ], [
                'bulan.not_in' => 'Pilih bulan terlebih dahulu.',
            ]);

            return redirect()->route('datacenter-organik-iklan.step2', [
                'bulan' => $validated['bulan'],
                'tahun' => $validated['tahun']
            ]);
        }
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan.step1')
        <x-app.container>
            <div class="flex items-center justify-between mb-5">
                <x-app.heading title="Langkah 1" description="Persiapkan file performa produk yang diunduh di Seller Center Shopee. Silakan ikuti petunjuk dibawah ini." :border="false" />
            </div>
            <div class="overflow-x-auto border rounded-lg">

            </div>
            <div class="flex flex-col rounded py-3 px-3">
                <div class="mb-2 flex gap-2">
                <span class="mb-2 h-[15px] flex-1 rounded-xl bg-black"></span>
                <span class="mb-2 h-[15px] flex-1 rounded-xl bg-gray-100"></span>
                <span class="mb-2 h-[15px] flex-1 rounded-xl bg-gray-100"></span>
                </div>
                <small>2 langkah lagi</small>
            </div>

            <div>
                <div class="flex mt-8">
                <form wire:submit="step2">
                    <div class="space-y-6 border-l-2 border-dashed">
                    <div class="relative w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-6">
                        <h4 class="font-bold text-black">1. Pilih Bulan Laporan Performa Toko</h4>
                        <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Silakan pilih bulan dan tahun laporan performa toko yang ingin dilihat.</p>
                        <div class="flex flex-row mt-2">
                            <div class="basis-1/8 mr-3">
                                <select wire:model="tahun" wire:change="$refresh" wire:key="tahun-select">
                                    {{-- Loop static untuk tahun --}}
                                    @foreach([2024, 2025] as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="basis-1/8 mr-3">
                                <select wire:model="bulan" wire:change="onBulanChange" class="@error('bulan') border-red-500 @enderror">
                                    <option value="0">Pilih Bulan</option>
                                    @foreach($this->getAvailableMonths() as $num => $name)
                                        <option value="{{ $num }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                
                                @error('bulan')
                                    <div class="mt-1 text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                        <small class="text-red-600">catatan: jika bulan tidak muncul, maka laporan bulan tersebut sudah diinput.</small>
                        </div>
                    </div>
                    <div class="relative w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-6">
                        <h4 class="font-bold text-black">2. Ganti Periode Data</h4>
                        <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Pilih periode data yang ingin di unduh. Silakan ikuti petunjuk pada gambar dibawah ini.</p>
                        <img class="mt-2 max-w-xl rounded-lg shadow-xl dark:shadow-gray-800" src="{{ asset('images/shopee') }}/performa-produk-1.webp" alt="">
                        </div>
                    </div>
                    <div class="relative w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-6">
                        <h4 class="font-bold text-black">3. Download File</h4>
                        <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Pilih periode data yang ingin di unduh. Silakan ikuti petunjuk pada gambar dibawah ini.</p>
                        <img class="mt-2 max-w-xl rounded-lg shadow-xl dark:shadow-gray-800" src="{{ asset('images/shopee') }}/performa-produk-2.webp" alt="">
                        </div>
                    </div>
                    <div class="relative w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-6">
                        <h4 class="font-bold text-black">4. Masuk ke Seller Center Shopee</h4>
                        <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Masuk ke menu Performa Toko > Produk > Performa Produk untuk mengunduh file laporan performa produk. Untuk akses lebih cepat, silakan klik tombol dibawah ini.</p>
                        <x-button class="mt-2" tag="a" href="https://seller.shopee.co.id/datacenter/product/performance" target="_blank">Buka Seller Center Shopee</x-button>
                        </div>
                    </div>
                    <div class="relative w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-6">
                            <h4 class="font-bold text-black">Ayo semangat, masih ada 2 langkah lagi.</h4>
                            <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Klik tombol selanjutnya untuk menuju ke langkah berikutnya</p>
                            <button 
                                type="submit" 
                                class="mt-2 px-4 py-2 bg-black text-white rounded-md transition-all
                                    @if(!$isSubmitDisabled) hover:bg-gray-800 cursor-pointer @else bg-opacity-50 cursor-not-allowed @endif"
                                @disabled($isSubmitDisabled)
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove>Selanjutnya</span>
                                <span wire:loading>
                                    <i class="fa-solid fa-spinner animate-spin"></i> Memproses...
                                </span>
                            </button>

                            @if($isSubmitDisabled && $bulan == 0)
                                <div class="mt-1 text-red-600 text-sm">
                                    Silakan pilih bulan terlebih dahulu
                                </div>
                            @endif
                        </div>
                    </div>
                    </div>
                </form>
                </div>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>
