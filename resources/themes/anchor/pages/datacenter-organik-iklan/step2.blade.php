<?php
    use Filament\Notifications\Notification;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Illuminate\Http\Request;
    use Carbon\Carbon;

    middleware('auth');
    name('datacenter-organik-iklan.step2');

    new class extends Component
    {
        public $bulan;
        public $tahun;
        public $namaBulan;
        public $tglMulai;
        public $tglAkhir;

        public function mount(Request $request)
        {
            $this->bulan = $request->query('bulan');
            $this->tahun = $request->query('tahun');

            if ($this->bulan) {
            $this->namaBulan = Carbon::createFromDate(null, $this->bulan, 1)
                ->locale('id')
                ->isoFormat('MMMM');
            } else {
                $this->namaBulan = '- belum pilih bulan -';
            }

            $this->tglMulai = Carbon::create($this->tahun, $this->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->timestamp;
            $this->tglAkhir = Carbon::create($this->tahun, $this->bulan, Carbon::create($this->tahun, $this->bulan)->daysInMonth, 23, 59, 59, 'Asia/Jakarta')->timestamp;

        }
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan.step2')
    <x-app.container>
        <div class="flex items-center justify-between mb-5">
            <x-app.heading title="Langkah 2" description="Persiapkan file iklan produk yang diunduh di Seller Center Shopee. Silakan ikuti petunjuk dibawah ini." :border="false" />
        </div>
        <div class="overflow-x-auto border rounded-lg">

        </div>
        <!-- progress bar -->
        <div class="flex flex-col rounded py-3 px-3">
            <div class="mb-2 flex gap-2">
            <span class="mb-2 h-[15px] flex-1 rounded-xl bg-black"></span>
            <span class="mb-2 h-[15px] flex-1 rounded-xl bg-black"></span>
            <span class="mb-2 h-[15px] flex-1 rounded-xl bg-gray-100"></span>
            </div>
            <small>1 langkah lagi</small>
        </div>

        <div>
            <!-- Created By Joker Banny -->
            <div class="flex mt-8">
                <div class="space-y-6 border-l-2 border-dashed">
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                        <h4 class="font-bold text-black">1. Anda memilih Laporan</h4>
                        <p class="mt-2 max-w-screen-sm text-sm text-gray-500"></p>
                        <div class="flex flex-row mt-2">
                            <div class="mr-3 text-red-600 font-bold text-2xl">
                                {{ $namaBulan }} {{ $tahun }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">2. Masuk ke Seller Center Shopee</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Masuk ke menu Pusat Promosi > Iklan Shopee > Iklan Produk untuk mengunduh file laporan performa iklan. Untuk akses lebih cepat, silakan klik tombol dibawah ini.</p>
                    <x-button class="mt-2" tag="a" href="https://seller.shopee.co.id/portal/marketing/pas/index/?type=product_homepage&from={{$tglMulai}}&to={{$tglAkhir}}&group=custom&offset=360" target="_blank">Buka Seller Center Shopee</x-button>
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">3. Ganti Periode Data</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Pilih periode data yang ingin di unduh. Silakan ikuti petunjuk pada gambar dibawah ini.</p>
                    <img class="mt-2" src="{{ asset('images/shopee') }}/iklan-1.webp" alt="">
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">4. Pilih Data Iklan produk</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Klik Download Data > Pilih data iklan produk. Tunggu hingga proses selesai atau muncul tombol Download.</p>
                    <img class="mt-2" src="{{ asset('images/shopee') }}/iklan-2.webp" alt="">
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">5. Download File</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Pilih file yang sudah dibuat oleh shopee. Silakan ikuti petunjuk pada gambar dibawah ini.</p>
                    <img class="mt-2" src="{{ asset('images/shopee') }}/iklan-3.webp" alt="">
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">Ayo semangat, masih ada 3 langkah lagi.</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Klik tombol selanjutnya untuk menuju ke langkah berikutnya</p>
                    <div class="mt-4">
                        <a class="mt-2 px-4 py-2 bg-black text-white font-bold rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50" href="/datacenter-organik-iklan/step1">Sebelumnya</a>
                        <a class="mt-2 px-4 py-2 bg-black text-white font-bold rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50" href="/datacenter-organik-iklan/step3?bulan={{$bulan}}&tahun={{$tahun}}">Selanjutnya</a>
                    </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </x-app.container>

    @endvolt
</x-layouts.app>
