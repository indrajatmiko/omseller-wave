<?php
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use App\Models\PerformaToko;
    use App\Models\PerformaIklan;
    use Illuminate\Support\Facades\DB;
    use App\Helpers\NumberFormatter;
    use Muhanz\Shoapi\Facades\Shoapi;

    middleware('auth');
    name('datacenter-organik-iklan');

    new class extends Component {
        public $tahunAktif;
        public $bulanAktif;
        public $isProcessing = false; // Status proses
        public $progress = 0; // Persentase progress
        public $bulanTersedia = [];
        public $wasProcessing = false;
        
        public function mount() {
            $this->bulanAktif = now()->month;
            $this->tahunAktif = now()->year;
            $this->checkProcessingStatus();
            $this->loadBulanTersedia();
        }
        
        public function checkProcessingStatus() {
            // Cek status processing
            $previousStatus = $this->isProcessing;
            $this->isProcessing = DB::table('jobs')->where('status', 'processing')->exists();

            // Jika status berubah dari processing ke selesai
            if($previousStatus && !$this->isProcessing) {
                $this->clearBulanCache(); // Bersihkan cache
            }

            // Contoh: Hitung progress (misalnya berdasarkan jumlah job selesai)
            $totalJobs = DB::table('jobs')->count();
            $completedJobs = DB::table('jobs')->where('status', 'completed')->count();
            $this->progress = $totalJobs > 0 ? ($completedJobs / $totalJobs) * 100 : 0;
        }

        protected function loadBulanTersedia() {
            $userId = auth()->id();
            $cacheKey = "bulan_tersedia_{$userId}_{$this->tahunAktif}";

            $this->bulanTersedia = cache()->remember($cacheKey, 60 * 60, function () use ($userId) {
                return PerformaToko::where('user_id', $userId)
                    ->where('tahun', $this->tahunAktif)
                    ->pluck('bulan')
                    ->toArray();
            });
        }

        public function switchYear($year) {
            $this->tahunAktif = $year;
            $this->clearBulanCache();
            $this->loadBulanTersedia();
        }
        
        protected function clearBulanCache() {
            $userId = auth()->id();
            $tahun = $this->tahunAktif;
            cache()->forget("bulan_tersedia_{$userId}_{$tahun}");
        }

        public function with(): array {
            return [
                'isProcessing' => $this->isProcessing,
                'progress' => $this->progress,
                'tahunAktif' => $this->tahunAktif,
                'bulanTersedia' => $this->bulanTersedia,
            ];
        }
        
        
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan')
        <x-app.container>
            <div class="flex items-center justify-between mb-5">
                <div class="flex gap-4">
                    <x-app.heading 
                        title="Data Organik vs Iklan {{ $tahunAktif}}" 
                        description="Mengetahui seberapa besar porsi omzet produk yang didorong oleh iklan.
" 
                        :border="false" 
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <x-button wire:click="switchYear({{ $tahunAktif - 1 }})">{{ $tahunAktif - 1 }}</x-button>
                    <x-button wire:click="switchYear({{ $tahunAktif + 1 }})" outlined>{{ $tahunAktif + 1 }}</x-button>
                    <x-button tag="a" href="/datacenter-organik-iklan/step1">Upload Data</x-button>
                </div>
            </div>
            
            <!-- Tambahkan polling untuk memeriksa status upload -->
            <div class="mt-8 p-2 border rounded-lg bg-white shadow-sm" @poll.60s>
                @if($isProcessing)
                    <div class="flex items-center gap-2">
                        <span class="text-gray-600">File sedang diproses...</span>
                        <div class="w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <!-- Progress Bar -->
                    <div class="relative w-full h-4 bg-gray-200 rounded-full overflow-hidden">
                        <div class="absolute top-0 left-0 h-full bg-blue-500 transition-all duration-500" style="width: {{ $progress }}%;"></div>
                    </div>
                    <!-- Progress Bar -->
                    <div class="relative w-full h-4 bg-gray-200 rounded-full overflow-hidden">
                        <div class="absolute top-0 left-0 h-full bg-blue-500 transition-all duration-500" style="width: {{ $progress }}%;"></div>
                    </div>
                    <small class="text-gray-500">Halaman akan otomatis diperbarui setiap 1 menit.</small>
                @else
                    <div class="text-green-600 font-medium">Proses selesai! Data telah berhasil diunggah.</div>
                @endif
                        
            </div>
            <div class="flex flex-col rounded py-3 px-3">
                {{ Shoapi::call('shop')->access('auth_partner')->getUrl(); }}
                <div class="mb-2 flex gap-2">
                    @php
                        $bulanNama = [
                            1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR',
                            5 => 'MEI', 6 => 'JUN', 7 => 'JUL', 8 => 'AGU',
                            9 => 'SEP', 10 => 'OKT', 11 => 'NOV', 12 => 'DES'
                        ];
                    @endphp
                    
                    @foreach(range(1, 12) as $bulan)
                        <span class="mb-2 h-[25px] flex-1 rounded-xl text-center
                            {{ in_array($bulan, $bulanTersedia) ? 'bg-black text-white shadow-md' : 'bg-gray-100 text-white shadow-md' }}">
                            {{ $bulanNama[$bulan] }}
                        </span>
                    @endforeach
                </div>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-xl flex items-center justify-between mt-4">
                <div class="flex space-x-6 items-center">
                    <img src="https://i.pinimg.com/originals/25/0c/a0/250ca0295215879bd0d53e3a58fa1289.png" class="w-auto h-24 rounded-lg"/>
                    <div>
                        <p class="font-semibold text-base">Kontribusi Penjualan Iklan</p>
                        <p class="font-semibold text-sm text-gray-400">Mengetahui seberapa besar porsi omzet produk yang didorong oleh iklan.
                        </p>
                    </div>              
                </div>
                   
                <div class="flex space-x-2 items-center">
                    <div class="bg-gray-300 rounded-md p-2 flex items-center">
                        <a href="datacenter-organik-iklan/kontribusi-penjualan-iklan"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"></path></svg></a>
                    </div>
                </div>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-xl flex items-center justify-between mt-6">
                <div class="flex space-x-6 items-center">
                    <img src="https://i.pinimg.com/originals/25/0c/a0/250ca0295215879bd0d53e3a58fa1289.png" class="w-auto h-24 rounded-lg"/>
                    <div>
                        <p class="font-semibold text-base">Kontribusi Unit Terjual Iklan</p>
                        <p class="font-semibold text-sm text-gray-400">Mengetahui seberapa besar porsi unit terjual produk yang didorong oleh iklan.
                        </p>
                    </div>              
                </div>
                   
                <div class="flex space-x-2 items-center">
                    <div class="bg-gray-300 rounded-md p-2 flex items-center">
                        <a href="datacenter-organik-iklan/kontribusi-unit-terjual-iklan"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"></path></svg></a>
                    </div>
                </div>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-xl flex items-center justify-between mt-6">
                <div class="flex space-x-6 items-center">
                    <img src="https://i.pinimg.com/originals/25/0c/a0/250ca0295215879bd0d53e3a58fa1289.png" class="w-auto h-24 rounded-lg"/>
                    <div>
                        <p class="font-semibold text-base">Blended ACOS</p>
                        <p class="font-semibold text-sm text-gray-400">Mengukur efisiensi biaya iklan relatif terhadap seluruh penjualan produk. Rumus: (Total Biaya Iklan / Total Penjualan) × 100%
                        </p>
                    </div>              
                </div>
                   
                <div class="flex space-x-2 items-center">
                    <div class="bg-gray-300 rounded-md p-2 flex items-center">
                        <a href="datacenter-organik-iklan/blended-acos"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"></path></svg></a>
                    </div>
                </div>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>
