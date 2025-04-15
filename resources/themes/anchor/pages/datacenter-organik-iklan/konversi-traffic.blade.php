<?php
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};
use App\Models\PerformaToko;
use App\Models\PerformaIklan;
use Illuminate\Support\Carbon;

middleware('auth');
name('konversi-traffic');

new class extends Component {
    public $tahunAktif;
    protected $userId;

    public function mount()
    {
        $this->userId = auth()->id();
        $this->tahunAktif = date('Y');
    }

    public function switchYear($tahun)
    {
        $this->tahunAktif = $tahun;
    }

    public function getMonthlyDataProperty()
    {
        $months = range(1, 12);
        $data = [];

        // Preload data untuk seluruh tahun
        $iklanData = PerformaIklan::where('user_id', $this->userId)
            ->whereYear('tanggal_mulai', $this->tahunAktif)
            ->get()
            ->groupBy(function($item) {
                return Carbon::parse($item->tanggal_mulai)->month;
            });

        $tokoData = PerformaToko::where('user_id', $this->userId)
            ->where('tahun', $this->tahunAktif)
            ->get()
            ->groupBy('bulan');

        foreach ($months as $month) {
            // Hitung konversi iklan
            $monthIklan = $iklanData->get($month, collect());
            
            $totalKonversiIklan = $monthIklan->sum('konversi');
            $totalKlikIklan = $monthIklan->sum('jumlah_klik');
            
            $konversiIklan = $totalKlikIklan > 0 
                ? ($totalKonversiIklan / $totalKlikIklan) * 100 
                : 0;

            // Hitung konversi keseluruhan
            $monthToko = $tokoData->get($month, collect());
            
            $totalPembeli = $monthToko->sum('total_pembeli_pesanan_siap_dikirim');
            $totalPengunjung = $monthToko->sum('pengunjung_produk_kunjungan');
            
            $konversiKeseluruhan = $totalPengunjung > 0 
                ? ($totalPembeli / $totalPengunjung) * 100 
                : 0;

            $data[] = [
                'month' => $month,
                'iklan' => round($konversiIklan, 2),
                'keseluruhan' => round($konversiKeseluruhan, 2),
            ];
        }

        return $data;
    }
}
?>
<x-layouts.app>
    @volt('konversi-traffic')
        <x-app.container>
            <div class="flex items-center justify-between mb-5">
                <x-app.heading 
                    title="Konversi Traffic" 
                    description="Membandingkan seberapa efektif traffic dari iklan dalam menghasilkan penjualan dibandingkan traffic keseluruhan."
                    :border="false"
                />
                <div class="flex gap-2">
                    <x-button wire:click="switchYear({{ $tahunAktif - 1 }})">{{ $tahunAktif - 1 }}</x-button>
                    <x-button wire:click="switchYear({{ $tahunAktif + 1 }})" outlined>{{ $tahunAktif + 1 }}</x-button>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bulan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Konversi Iklan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Konversi Keseluruhan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perbandingan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($this->monthlyData as $data)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ Carbon::createFromDate($tahunAktif, $data['month'])->translatedFormat('F') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $data['iklan'] }}%</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $data['keseluruhan'] }}%</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($data['iklan'] > $data['keseluruhan'])
                                        <span class="text-green-600">Iklan Lebih Efektif</span>
                                    @elseif($data['iklan'] < $data['keseluruhan'])
                                        <span class="text-red-600">Organik Lebih Efektif</span>
                                    @else
                                        <span class="text-gray-500">Sama</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>