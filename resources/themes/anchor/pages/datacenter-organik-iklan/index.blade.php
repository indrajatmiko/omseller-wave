<?php
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use App\Models\PerformaToko;
    use App\Models\PerformaIklan;
    use Illuminate\Support\Facades\DB;
    use App\Helpers\NumberFormatter;

    middleware('auth');
    name('datacenter-organik-iklan');

    new class extends Component {
        public $tahunAktif;
        public $bulanAktif;
        public $isProcessing = false; // Status proses
        public $progress = 0; // Persentase progress
        
        public function mount() {
            $this->bulanAktif = now()->month;
            $this->tahunAktif = now()->year;
            $this->checkProcessingStatus();
        }
        
        public function checkProcessingStatus() {
            // Contoh: Periksa status dari database atau queue
            $this->isProcessing = DB::table('jobs')->where('status', 'processing')->exists();

            // Contoh: Hitung progress (misalnya berdasarkan jumlah job selesai)
            $totalJobs = DB::table('jobs')->count();
            $completedJobs = DB::table('jobs')->where('status', 'completed')->count();
            $this->progress = $totalJobs > 0 ? ($completedJobs / $totalJobs) * 100 : 0;
        }

        public function switchYear($year) {
            $this->tahunAktif = $year;
        }
        
        public function getSalesData() {
            $dataToko = PerformaToko::select(
                    'bulan',
                    'tahun',
                    DB::raw('SUM(penjualan_pesanan_siap_dikirim) as total_penjualan'),
                    DB::raw('0 as penjualan_iklan'),
                    DB::raw('0 as biaya_iklan')
                )
                ->where('tingkat_konversi_pesanan_siap_dikirim', '>', 0)
                ->where('tahun', $this->tahunAktif)
                ->groupBy('tahun', 'bulan');
                
            $dataIklan = PerformaIklan::select(
                    'bulan',
                    'tahun',
                    DB::raw('0 as total_penjualan'),
                    DB::raw('SUM(omzet_penjualan) as penjualan_iklan'),
                    DB::raw('SUM(biaya) as biaya_iklan')
                )
                ->where('tahun', $this->tahunAktif)
                ->groupBy('tahun', 'bulan');
                
            return $dataToko->unionAll($dataIklan)
                ->get()
                ->groupBy('bulan')
                ->map(function ($monthData) {
                    return [
                        'total_penjualan' => $monthData->sum('total_penjualan'),
                        'penjualan_iklan' => $monthData->sum('penjualan_iklan'),
                        'biaya_iklan' => $monthData->sum('biaya_iklan')
                    ];
                });
        }
        
        public function calculateMetrics() {
            $currentData = $this->getSalesData();
            $prevYearData = $this->getYearData($this->tahunAktif - 1);
            
            $metrics = [];
            
            foreach ($currentData as $month => $data) {
                // MoM Calculation
                $prevMonth = $month - 1;
                $mom = $prevMonth > 0 && isset($currentData[$prevMonth]) ? 
                    (($data['total_penjualan'] - $currentData[$prevMonth]['total_penjualan']) / 
                    $currentData[$prevMonth]['total_penjualan'] * 100) : 0;
                
                // YoY Calculation
                $yoy = isset($prevYearData[$month]) ? 
                    (($data['total_penjualan'] - $prevYearData[$month]['total_penjualan']) / 
                    $prevYearData[$month]['total_penjualan'] * 100) : 0;
                
                $metrics[$month] = [
                    'total' => $data['total_penjualan'],
                    'iklan' => $data['penjualan_iklan'],
                    'biaya' => $data['biaya_iklan'],
                    'persen_iklan' => $data['total_penjualan'] ? 
                        ($data['penjualan_iklan'] / $data['total_penjualan'] * 100) : 0,
                    'mom' => $mom,
                    'yoy' => $yoy
                ];
            }
            
            return $metrics;
        }
        
        protected function getYearData($year) {
            return Cache::remember("year_data_{$year}", 3600, function () use ($year) {

            });
        }
        protected function calculateQoQ() {
            $currentData = $this->getSalesData();
            $currentMonth = $this->bulanAktif;
            
            $quarters = [
                1 => ['months' => [1,2,3], 'total' => 0, 'iklan' => 0, 'biaya' => 0, 'available' => false],
                2 => ['months' => [4,5,6], 'total' => 0, 'iklan' => 0, 'biaya' => 0, 'available' => false],
                3 => ['months' => [7,8,9], 'total' => 0, 'iklan' => 0, 'biaya' => 0, 'available' => false],
                4 => ['months' => [10,11,12], 'total' => 0, 'iklan' => 0, 'biaya' => 0, 'available' => false],
            ];

            foreach ($quarters as $q => &$quarter) {
                $availableMonths = array_filter($quarter['months'], function($m) use ($currentMonth) {
                    return $m <= $currentMonth;
                });
                
                if (!empty($availableMonths)) {
                    $quarter['available'] = true;
                    foreach ($availableMonths as $month) {
                        $quarter['total'] += $currentData[$month]['total_penjualan'] ?? 0;
                        $quarter['iklan'] += $currentData[$month]['penjualan_iklan'] ?? 0;
                        $quarter['biaya'] += $currentData[$month]['biaya_iklan'] ?? 0; // Tambah biaya
                    }
                }
            }
            
            return $quarters;
        }
        
        public function with(): array {
            return [
                'metrics' => $this->calculateMetrics(),
                'qoq' => $this->calculateQoQ(),
                'isProcessing' => $this->isProcessing,
                'progress' => $this->progress,
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
                        description="Perbandingan data " 
                        :border="false" 
                    />
                    <x-button wire:click="switchYear({{ $tahunAktif - 1 }})">{{ $tahunAktif - 1 }}</x-button>
                    <x-button wire:click="switchYear({{ $tahunAktif + 1 }})" outlined>{{ $tahunAktif + 1 }}</x-button>
                </div>
                <x-button tag="a" href="/datacenter-organik-iklan/step1">Upload Data</x-button>
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
                    </div>
                @else
                    <div class="text-green-600 font-medium">Proses selesai! Data telah berhasil diunggah.</div>
                @endif
            </div>

            <div class="mt-4 overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bulan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Penjualan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Penjualan Iklan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">% Iklan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Biaya Iklan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">MoM</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">YoY</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($metrics as $month => $data)
                        @php
                            $total = $data['total'] ?? 0;
                            $iklan = $data['iklan'] ?? 0;
                            $biaya = $data['biaya'] ?? 0;
                            $persenIklan = $total > 0 ? ($iklan / $total * 100) : 0;
                            $isCurrentMonth = $month == $bulanAktif;
                        @endphp
                        <tr @if($isCurrentMonth) class="bg-blue-50" @endif>
                            <td class="px-6 py-4 font-medium">
                                {{ DateTime::createFromFormat('!m', $month)->format('F') }}
                                @if($isCurrentMonth) <span class="text-blue-500">(Bulan Aktif)</span> @endif
                            </td>
                            <td class="px-6 py-4 text-right">@moneyShort($total)</td>
                            <td class="px-6 py-4 text-right">@moneyShort($iklan)</td>
                            <td class="px-6 py-4 text-right {{ $persenIklan > 50 ? 'text-red-500' : 'text-green-500' }}">
                                {{ number_format($persenIklan, 2) }}%
                            </td>
                            <td class="px-6 py-4 text-right text-orange-600">@moneyShort($biaya)</td>
                            <td class="px-6 py-4 text-right {{ ($data['mom'] ?? 0) >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ number_format($data['mom'] ?? 0, 2) }}%
                            </td>
                            <td class="px-6 py-4 text-right {{ ($data['yoy'] ?? 0) >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ number_format($data['yoy'] ?? 0, 2) }}%
                            </td>
                        </tr>
                        @endforeach
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 font-bold">Total {{ $tahunAktif }}</td>
                            <td class="px-6 py-4 text-right font-bold">@moneyShort(array_sum(array_column($metrics, 'total')))</td>
                            <td class="px-6 py-4 text-right font-bold">@moneyShort(array_sum(array_column($metrics, 'iklan')))</td>
                            <td></td>
                            <td class="px-6 py-4 text-right font-bold text-orange-600">@moneyShort(array_sum(array_column($metrics, 'biaya')))</td>
                            <td class="px-6 py-4 text-right font-bold">
                                @php
                                    $totalYear = array_sum(array_column($metrics, 'total'));
                                    $totalIklanYear = array_sum(array_column($metrics, 'iklan'));
                                    $persenIklanYear = $totalYear > 0 ? ($totalIklanYear / $totalYear * 100) : 0;
                                @endphp
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <small>Total Penjualan : Total nilai dari pesanan siap dikirim dalam jangka waktu tertentu, termasuk pesanan non-COD telah dibayar dan pesanan COD terkonfirmasi, termasuk penjualan yang dibatalkan dan dikembalikan.</small>
            
            <!-- Tambahkan section untuk QoQ -->
            <div class="mt-8 p-4 border rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Perbandingan Quarter-over-Quarter (QoQ)</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($qoq as $quarter => $data)
                    <div class="p-4 border rounded bg-white shadow-sm">
                        <h4 class="font-bold text-gray-700">
                            Q{{ $quarter }} {{ $tahunAktif }}
                            @if(!$data['available']) <span class="text-gray-400"></span> @endif
                        </h4>
                        
                        @if($data['available'])
                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Penjualan:</span>
                                <span class="font-medium">@moneyShort($data['total'])</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Penjualan Iklan:</span>
                                <span class="font-medium">@moneyShort($data['iklan'])</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Biaya Iklan:</span>
                                <span class="font-medium text-orange-600">@moneyShort($data['biaya'])</span>
                            </div>
                            <div class="flex justify-between border-t pt-1">
                                <span class="text-gray-600">Kontribusi Iklan:</span>
                                <span class="{{ ($data['total'] > 0 && ($data['iklan']/$data['total']*100) > 30) ? 'text-red-500' : 'text-green-500' }}">
                                    {{ $data['total'] > 0 ? number_format(($data['iklan']/$data['total']*100), 2) : 0 }}%
                                </span>
                            </div>
                        </div>
                        @else
                        <div class="mt-2 text-center py-4 bg-gray-50 rounded">
                            <span class="text-gray-400 text-sm">Data periode ini belum tersedia</span>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>
