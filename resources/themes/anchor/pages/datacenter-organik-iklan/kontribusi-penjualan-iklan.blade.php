<?php
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use App\Models\PerformaToko;
    use App\Models\PerformaIklan;
    use Illuminate\Support\Facades\DB;
    use App\Helpers\NumberFormatter;

    middleware('auth');
    name('kontribusi-penjualan-iklan');

    new class extends Component {
        public $tahunAktif;
        public $bulanAktif;
        
        public function mount() {
            $this->bulanAktif = now()->month;
            $this->tahunAktif = now()->year;
        }
        
        public function switchYear($year) {
            $this->tahunAktif = $year;
        }
        
        public function getSalesData() {
            return Cache::remember("sales_data_{$this->tahunAktif}", 3600, function () {
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
            return Cache::remember("qoq_data_{$this->tahunAktif}", 3600, function () {
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
                            $quarter['biaya'] += $currentData[$month]['biaya_iklan'] ?? 0;
                        }
                    }
                }
                
                return $quarters;
            });
        }
        
        public function with(): array {
            return [
                'metrics' => $this->calculateMetrics(),
                'qoq' => $this->calculateQoQ(),
            ];
        }
        
    }
?>

<x-layouts.app>
    @volt('kontribusi-penjualan-iklan')
        <x-app.container>
            <div class="flex items-center justify-between mb-5">
                <div class="flex gap-4">
                    <x-app.heading 
                        title="Organik vs Iklan - Kontribusi Penjualan Iklan {{ $tahunAktif}}" 
                        description="Mengetahui seberapa besar porsi omzet produk yang didorong oleh iklan.
" 
                        :border="false" 
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <x-button wire:click="switchYear({{ $tahunAktif - 1 }})">{{ $tahunAktif - 1 }}</x-button>
                    <x-button wire:click="switchYear({{ $tahunAktif + 1 }})" outlined>{{ $tahunAktif + 1 }}</x-button>
                </div>
            </div>

            @php
                $totalYear = array_sum(array_column($metrics, 'total'));
                $totalIklanYear = array_sum(array_column($metrics, 'iklan'));
                $persenIklanYear = $totalYear > 0 ? ($totalIklanYear / $totalYear * 100) : 0;
                $totalBiayaYear = array_sum(array_column($metrics, 'biaya'));
            @endphp
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md border">
                    <dt class="text-sm font-medium text-gray-500">Total Biaya Iklan</dt>
                    <dd class="mt-1 text-3xl font-semibold text-orange-600">@moneyShort($totalBiayaYear)</dd>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border">
                    <dt class="text-sm font-medium text-gray-500">Total Penjualan</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">@moneyShort($totalYear)</dd>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border">
                    <dt class="text-sm font-medium text-gray-500">Kontribusi Iklan</dt>
                    <dd class="mt-1 text-3xl font-semibold {{ $persenIklanYear > 50 ? 'text-red-500' : 'text-green-500' }}">
                        {{ number_format($persenIklanYear, 2) }}%
                    </dd>
                </div>
            </div>
            
            <!-- Tabel tanpa footer total -->
            <div class="mt-4 overflow-x-auto border rounded-lg shadow-md">
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
                            $totalClass = $total > 0 ? 'text-green-500' : 'text-red-500';
                            $iklanClass = $iklan > 0 ? 'text-green-500' : 'text-red-500';
                        @endphp
                        <tr @if($isCurrentMonth) class="bg-blue-50" @endif>
                            <td class="px-6 py-4 font-medium">
                                {{ DateTime::createFromFormat('!m', $month)->format('F') }}
                                @if($isCurrentMonth) <span class="text-blue-500">(Bulan Aktif)</span> @endif
                            </td>
                            <td class="px-6 py-4 text-right {{ $totalClass }}">@moneyShort($total)</td>
                            <td class="px-6 py-4 text-right {{ $iklanClass }}">@moneyShort($iklan)</td>
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
                    </tbody>
                </table>
            </div>
            <small>Total Penjualan : Total nilai dari pesanan siap dikirim dalam jangka waktu tertentu, termasuk pesanan non-COD telah dibayar dan pesanan COD terkonfirmasi, termasuk penjualan yang dibatalkan dan dikembalikan.</small>
            
            <div class="mt-8 flex justify-between">
                <!-- Tombol Analisa dengan AI -->
                <button 
                    class="w-1/2 mr-2 px-4 py-2 bg-white text-black font-bold rounded-md outline outline-1"
                    onclick="window.location.href='{{ route('datacenter-organik-iklan.ai-analysis', ['persenIklanYear' => $persenIklanYear]) }}'"
                outlined>
                    Tanya Generated AI
                </button>
            
                <!-- Tombol Live AI -->
                <button 
                    class="w-1/2 mr-2 px-4 py-2 bg-black text-white font-bold rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50"
                    onclick="window.location.href='{{ route('datacenter-organik-iklan.ai-analysis-blended-acos', ['persenIklanYear' => $persenIklanYear]) }}'"
                >
                    Tanya Live AI
                </button>
            </div>
            <small class="mt-2">* Untuk saran analisa yang lebih baik, silakan klik tombol Tanya Live AI.</small>
            <!-- Tambahkan section untuk QoQ -->
            <div class="mt-8 p-4 border rounded-lg shadow-md">
                <h3 class="text-lg font-semibold mb-4">Perbandingan Quarter-over-Quarter (QoQ)</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($qoq as $quarter => $data)
                    <div class="p-4 border rounded bg-white shadow-md">
                        <h4 class="font-bold text-gray-700">
                            Q{{ $quarter }} {{ $tahunAktif }}
                            @if(!$data['available']) <span class="text-gray-400"></span> @endif
                        </h4>
                        
                        @if($data['available'])
                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Penjualan:</span>
                                <span class="font-medium">
                                    @moneyShort($data['total'])
                                    @if(isset($qoq[$quarter - 1]) && $qoq[$quarter - 1]['available'])
                                        @php
                                            $prevTotal = $qoq[$quarter - 1]['total'];
                                            $indicator = $prevTotal > 0 ? ($data['total'] - $prevTotal) : 0;
                                        @endphp
                                        @if($indicator > 0)
                                            <span class="text-green-500">▲</span>
                                        @elseif($indicator < 0)
                                            <span class="text-red-500">▼</span>
                                        @endif
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Penjualan Iklan:</span>
                                <span class="font-medium">
                                    @moneyShort($data['iklan'])
                                    @if(isset($qoq[$quarter - 1]) && $qoq[$quarter - 1]['available'])
                                        @php
                                            $prevIklan = $qoq[$quarter - 1]['iklan'];
                                            $indicator = $prevIklan > 0 ? ($data['iklan'] - $prevIklan) : 0;
                                        @endphp
                                        @if($indicator > 0)
                                            <span class="text-green-500">▲</span>
                                        @elseif($indicator < 0)
                                            <span class="text-red-500">▼</span>
                                        @endif
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Biaya Iklan:</span>
                                <span class="font-medium text-orange-600">
                                    @moneyShort($data['biaya'])
                                    @if(isset($qoq[$quarter - 1]) && $qoq[$quarter - 1]['available'])
                                        @php
                                            $prevBiaya = $qoq[$quarter - 1]['biaya'];
                                            $indicator = $prevBiaya > 0 ? ($data['biaya'] - $prevBiaya) : 0;
                                        @endphp
                                        @if($indicator > 0)
                                            <span class="text-green-500">▲</span>
                                        @elseif($indicator < 0)
                                            <span class="text-red-500">▼</span>
                                        @endif
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between border-t pt-1">
                                <span class="text-gray-600 font-semibold">Kontribusi Iklan:</span>
                                <span class="{{ ($data['total'] > 0 && ($data['iklan']/$data['total']*100) > 30) ? 'text-red-500' : 'text-green-500' }}">
                                    {{ $data['total'] > 0 ? number_format(($data['iklan']/$data['total']*100), 2) : 0 }}%
                                </span>
                            </div>
                        </div>
                        @else
                        <div class="mt-2 text-center py-4 bg-gray-50 rounded">
                            <span class="text-gray-400 text-sm">Data kuartal ini belum tersedia
                            </span>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>
