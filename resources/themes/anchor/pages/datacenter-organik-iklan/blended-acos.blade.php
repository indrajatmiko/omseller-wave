<?php
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};
use App\Models\PerformaToko;
use App\Models\PerformaIklan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

middleware('auth');
name('blended-acos');

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

    protected function getMonthlyData() {
        return Cache::remember("blended_acos_monthly_{$this->tahunAktif}", 3600, function () {
            // Ambil bulan yang memiliki data di salah satu tabel
            $bulanAdaData = PerformaToko::where('tahun', $this->tahunAktif)
                ->select('bulan')
                ->unionAll(
                    PerformaIklan::where('tahun', $this->tahunAktif)
                        ->select('bulan')
                )
                ->groupBy('bulan')
                ->pluck('bulan')
                ->sort()
                ->values()
                ->toArray();

            // Ambil data iklan dan penjualan
            $iklan = PerformaIklan::select('bulan', DB::raw('SUM(biaya) as biaya_iklan'))
                ->where('tahun', $this->tahunAktif)
                ->groupBy('bulan')
                ->get()
                ->keyBy('bulan');

            $penjualan = PerformaToko::select('bulan', DB::raw('SUM(penjualan_pesanan_siap_dikirim) as total_penjualan'))
                ->where('tahun', $this->tahunAktif)
                ->where('tingkat_konversi_pesanan_siap_dikirim', '>', 0)
                ->groupBy('bulan')
                ->get()
                ->keyBy('bulan');

            $metrics = [];
            $prevAcos = null;

            foreach ($bulanAdaData as $month) {
                $biayaIklan = $iklan->get($month)?->biaya_iklan ?? 0;
                $totalPenjualan = $penjualan->get($month)?->total_penjualan ?? 0;
                $blendedAcos = $totalPenjualan ? ($biayaIklan / $totalPenjualan * 100) : 0;

                // Hitung MoM berdasarkan urutan data sebenarnya
                $mom = $prevAcos !== null ? (($blendedAcos - $prevAcos) / $prevAcos * 100) : 0;
                
                $metrics[$month] = [
                    'biaya_iklan' => $biayaIklan,
                    'total_penjualan' => $totalPenjualan,
                    'blended_acos' => $blendedAcos,
                    'mom' => $mom
                ];

                $prevAcos = $blendedAcos;
            }

            // Batasi bulan berjalan jika tahun aktif adalah tahun ini
            if ($this->tahunAktif == now()->year) {
                $metrics = array_filter($metrics, fn($m) => $m <= now()->month, ARRAY_FILTER_USE_KEY);
            }

            // Urutkan berdasarkan bulan
            ksort($metrics);
            
            return $metrics;
        });
    }

    protected function calculateQoQ() {
        return Cache::remember("blended_acos_qoq_{$this->tahunAktif}", 3600, function () {
            $monthlyData = $this->getMonthlyData();
            $currentMonth = $this->bulanAktif;

            $quarters = [
                1 => ['months' => [1,2,3], 'biaya' => 0, 'penjualan' => 0, 'acos' => 0, 'available' => false],
                2 => ['months' => [4,5,6], 'biaya' => 0, 'penjualan' => 0, 'acos' => 0, 'available' => false],
                3 => ['months' => [7,8,9], 'biaya' => 0, 'penjualan' => 0, 'acos' => 0, 'available' => false],
                4 => ['months' => [10,11,12], 'biaya' => 0, 'penjualan' => 0, 'acos' => 0, 'available' => false],
            ];

            foreach ($quarters as $q => &$quarter) {
                $availableMonths = array_filter($quarter['months'], fn($m) => $m <= $currentMonth);
                
                if (!empty($availableMonths)) {
                    $quarter['available'] = true;
                    foreach ($availableMonths as $month) {
                        $quarter['biaya'] += $monthlyData[$month]['biaya_iklan'] ?? 0;
                        $quarter['penjualan'] += $monthlyData[$month]['total_penjualan'] ?? 0;
                    }
                    $quarter['acos'] = $quarter['penjualan'] ? 
                        ($quarter['biaya'] / $quarter['penjualan'] * 100) : 0;
                }
            }
            
            return $quarters;
        });
    }

    public function with(): array {
        return [
            'monthly' => $this->getMonthlyData(),
            'qoq' => $this->calculateQoQ(),
            'total' => [
                'biaya' => array_sum(array_column($this->getMonthlyData(), 'biaya_iklan')),
                'penjualan' => array_sum(array_column($this->getMonthlyData(), 'total_penjualan')),
                'acos' => array_sum(array_column($this->getMonthlyData(), 'total_penjualan')) ? 
                    (array_sum(array_column($this->getMonthlyData(), 'biaya_iklan')) / 
                    array_sum(array_column($this->getMonthlyData(), 'total_penjualan')) * 100) : 0
            ]
        ];
    }
}?>

<x-layouts.app>
    @volt('blended-acos')
        <x-app.container>
            <div class="flex items-center justify-between mb-5">
                <x-app.heading 
                    title="Blended ACOS {{ $tahunAktif}}" 
                    description="Mengukur efisiensi biaya iklan relatif terhadap seluruh penjualan produk. Rumus: (Total Biaya Iklan / Total Penjualan) × 100%"
                    :border="false"
                />
                <div class="flex gap-2">
                    <x-button wire:click="switchYear({{ $tahunAktif - 1 }})">{{ $tahunAktif - 1 }}</x-button>
                    <x-button wire:click="switchYear({{ $tahunAktif + 1 }})" outlined>{{ $tahunAktif + 1 }}</x-button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md border">
                    <dt class="text-sm font-medium text-gray-500">Total Biaya Iklan</dt>
                    <dd class="mt-1 text-3xl font-semibold text-orange-600">
                        @moneyShort($total['biaya'])
                    </dd>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border">
                    <dt class="text-sm font-medium text-gray-500">Total Penjualan</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">
                        @moneyShort($total['penjualan'])
                    </dd>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border">
                    <dt class="text-sm font-medium text-gray-500">Blended ACOS</dt>
                    <dd class="mt-1 text-3xl font-semibold {{ $total['acos'] > 15 ? 'text-red-500' : 'text-green-500' }}">
                        {{ number_format($total['acos'], 2) }}%
                    </dd>
                </div>
            </div>

            <!-- Monthly Table -->
            <div class="mt-4 border rounded-lg shadow-md overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bulan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Biaya Iklan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Penjualan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Blended ACOS</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">MoM</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php $prevData = null; @endphp
                        @foreach($monthly as $month => $data)
                        @php
                            $isCurrent = $month == $bulanAktif && $tahunAktif == now()->year;
                            $acosClass = $data['blended_acos'] > 15 ? 'text-red-500' : 'text-green-500';
                            $momClass = $data['mom'] >= 0 ? 'text-red-500' : 'text-green-500';
                    
                            // Warna untuk Biaya Iklan
                            $biayaClass = $prevData && $data['biaya_iklan'] < $prevData['biaya_iklan'] ? 'text-green-500' : 'text-red-500';
                    
                            // Warna untuk Penjualan
                            $penjualanClass = $prevData && $data['total_penjualan'] > $prevData['total_penjualan'] ? 'text-green-500' : 'text-red-500';
                        @endphp
                        <tr @if($isCurrent) class="bg-blue-50" @endif>
                            <td class="px-6 py-4 font-medium">
                                {{ DateTime::createFromFormat('!m', $month)->format('F') }}
                                @if($isCurrent) <span class="text-blue-500">(Bulan Ini)</span> @endif
                            </td>
                            <td class="px-6 py-4 text-right {{ $biayaClass }}">@moneyShort($data['biaya_iklan'])</td>
                            <td class="px-6 py-4 text-right {{ $penjualanClass }}">@moneyShort($data['total_penjualan'])</td>
                            <td class="px-6 py-4 text-right {{ $acosClass }}">{{ number_format($data['blended_acos'], 2) }}%</td>
                            <td class="px-6 py-4 text-right {{ $momClass }}">
                                {{ $data['mom'] ? number_format($data['mom'], 2).'%' : '-' }}
                            </td>
                        </tr>
                        @php $prevData = $data; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>

            <small>Total Penjualan : Total nilai dari pesanan siap dikirim dalam jangka waktu tertentu, termasuk pesanan non-COD telah dibayar dan pesanan COD terkonfirmasi, termasuk penjualan yang dibatalkan dan dikembalikan.</small>
            
            <div class="mt-8 flex justify-between">
                <!-- Tombol Analisa dengan AI -->
                <button 
                    class="w-1/2 mr-2 px-4 py-2 bg-white text-black font-bold rounded-md outline outline-1"
                    onclick="window.location.href='{{ route('datacenter-organik-iklan.ai-analysis-blended-acos', ['totalAcos' => $total['acos']]) }}'"
                outlined>
                    Tanya Generated AI
                </button>
            
                <!-- Tombol Live AI -->
                <button 
                    class="w-1/2 mr-2 px-4 py-2 bg-black text-white font-bold rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50"
                    onclick="window.location.href='{{ route('datacenter-organik-iklan.ai-analysis-blended-acos', ['totalAcos' => $total['acos']]) }}'"
                >
                    Tanya Live AI
                </button>
            </div>
            <small class="mt-2">* Untuk saran analisa yang lebih baik, silakan klik tombol Tanya Live AI.</small>
            
            <!-- QoQ Section -->
            <div class="border rounded-lg shadow-md p-4 mt-8">
                <h3 class="text-lg font-semibold mb-4">Analisis Per Kuartal (QoQ)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kolom Kiri (Q1 & Q2) -->
                    <div class="space-y-4">
                        @foreach([1,3] as $quarter)
                        @php $data = $qoq[$quarter] ?? null @endphp
                        @if($data)
                        <div class="bg-white p-4 rounded-lg border shadow-sm">
                            <h4 class="font-bold text-gray-700 mb-3 flex items-center justify-between">
                                <span>Q{{ $quarter }} {{ $tahunAktif }}</span>
                                @if(!$data['available'])
                                    <span class="text-sm font-normal text-gray-400"></span>
                                @endif
                            </h4>
                            
                            @if($data['available'])
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Biaya:</span>
                                    <span class="text-orange-600">@moneyShort($data['biaya'])</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Penjualan:</span>
                                    <span class="text-green-600">@moneyShort($data['penjualan'])</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t pt-2">
                                    <span>BLENDED ACOS:</span>
                                    <span class="{{ $data['acos'] > 15 ? 'text-red-500' : 'text-green-500' }}">
                                        {{ number_format($data['acos'], 2) }}%
                                    </span>
                                </div>
                                @if($quarter > 1 && isset($qoq[$quarter - 1]['available']) && $qoq[$quarter - 1]['available'])
                                @php
                                    $prevAcos = $qoq[$quarter - 1]['acos'];
                                    $change = $prevAcos ? (($data['acos'] - $prevAcos)/$prevAcos*100) : 0;
                                @endphp
                                {{-- <div class="text-sm text-gray-500 mt-2">
                                    vs Q{{ $quarter - 1}}:
                                    <span class="{{ $change >= 0 ? 'text-red-500' : 'text-green-500' }}">
                                        {{ number_format($change, 2) }}%
                                    </span>
                                </div> --}}
                                @endif
                            </div>
                            @else
                            <div class="mt-2 text-center py-4 bg-gray-50 rounded">
                                <span class="text-gray-400 text-sm">Data kuartal ini belum tersedia
                                </span>
                            </div>
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>

                    <!-- Kolom Kanan (Q3 & Q4) -->
                    <div class="space-y-4">
                        @foreach([2,4] as $quarter)
                        @php $data = $qoq[$quarter] ?? null @endphp
                        @if($data)
                        <div class="bg-white p-4 rounded-lg border shadow-sm">
                            <h4 class="font-bold text-gray-700 mb-3 flex items-center justify-between">
                                <span>Q{{ $quarter }} {{ $tahunAktif }}</span>
                                @if(!$data['available'])
                                    <span class="text-sm font-normal text-gray-400"></span>
                                @endif
                            </h4>
                            
                            @if($data['available'])
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Biaya:</span>
                                    <span class="text-orange-600">@moneyShort($data['biaya'])</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Penjualan:</span>
                                    <span class="text-green-600">@moneyShort($data['penjualan'])</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t pt-2">
                                    <span>BLENDED ACOS:</span>
                                    <span class="{{ $data['acos'] > 15 ? 'text-red-500' : 'text-green-500' }}">
                                        {{ number_format($data['acos'], 2) }}%
                                    </span>
                                </div>
                                @if($quarter > 1 && isset($qoq[$quarter - 1]['available']) && $qoq[$quarter - 1]['available'])
                                @php
                                    $prevAcos = $qoq[$quarter - 1]['acos'];
                                    $change = $prevAcos ? (($data['acos'] - $prevAcos)/$prevAcos*100) : 0;
                                @endphp
                                {{-- <div class="text-sm text-gray-500 mt-2">
                                    vs Q{{ $quarter - 1}}:
                                    <span class="{{ $change >= 0 ? 'text-red-500' : 'text-green-500' }}">
                                        {{ number_format($change, 2) }}%
                                    </span>
                                </div> --}}
                                @endif
                            </div>
                            @else
                            <div class="mt-2 text-center py-4 bg-gray-50 rounded">
                                <span class="text-gray-400 text-sm">Data kuartal ini belum tersedia
                                </span>
                            </div>
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        ACOS ≤ 15% (Sehat)
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                        ACOS > 15% (Perlu Evaluasi)
                    </div>
                </div>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>