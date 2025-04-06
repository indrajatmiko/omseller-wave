<?php
    use function Laravel\Folio\{middleware, name};
    use Livewire\Volt\Component;
    use Filament\Notifications\Notification;
    use Illuminate\Http\Request;
    use Carbon\Carbon;
    use Livewire\WithFileUploads;
    use Filament\Forms;

    use App\Jobs\ProcessPerformaToko; // Sesuaikan namespace
    use App\Jobs\ProcessPerformaIklan;

    middleware('auth');
    name('datacenter-organik-iklan.step3');

    new class extends Component
    {
        use WithFileUploads;
        public $bulan;
        public $tahun;
        public $namaBulan;
        public $tglMulai;
        public $tglAkhir;

        public $fileToko;
        public $fileIklan;

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

        public function uploadFile() {
            try {
                $this->validate([
                    'fileToko' => 'required|mimes:xlsx|max:10240',
                    'fileIklan' => [
                        'required',
                        'mimes:csv',
                        function ($attribute, $value, $fail) {
                            $csv = array_map('str_getcsv', file($value->getRealPath()));
                            
                            // Cek apakah baris ke-9 ada dan memiliki cukup kolom
                            if (!isset($csv[8]) || count($csv[8]) < 30) { // Sesuaikan angka 30 dengan jumlah kolom
                                $fail("Format CSV tidak valid. Pastikan menggunakan template dari Shopee!");
                            }
                        }
                    ]
                ]);

                // Ambil username dari CSV
                $csvPath = $this->fileIklan->getRealPath();
                $csvData = array_map('str_getcsv', file($csvPath));
                
                if (!isset($csvData[1][1])) {
                    throw new \Exception("Format CSV tidak valid. Pastikan username ada di B2.");
                }
                $username = $csvData[1][1];

                // Simpan file
                $pathToko = $this->fileToko->store('performa-toko');
                $pathIklan = $this->fileIklan->store('performa-iklan');

                // Dispatch jobs
                ProcessPerformaToko::dispatch($username, auth()->user()->id, $this->bulan, $this->tahun, $pathToko);
                ProcessPerformaIklan::dispatch($username, auth()->user()->id, $this->bulan, $this->tahun, $pathIklan);

                Notification::make()
                    ->success()
                    ->title('🎉 Upload Berhasil!')
                    ->body('Data sedang diproses di latar belakang')
                    ->sendToDatabase(auth()->user()); // Untuk notifikasi database

                return redirect()->route('datacenter-organik-iklan');

            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('🚨 Error!')
                    ->body($e->getMessage())
                    ->send();
                
                $this->reset('fileToko', 'fileIklan');
            }
        }

        private function extractCsvUsername($path) {
            $csv = array_map('str_getcsv', file($path));
            return $csv[1][1]; // Ambil B2 (baris 2, kolom 2)
        }
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan.step3')
    <x-app.container>
        <div class="flex items-center justify-between mb-5">
            <x-app.heading title="Langkah 3" description="Upload file performa toko dan performa iklan. Silakan ikuti petunjuk dibawah ini." :border="false" />
        </div>
        <div class="overflow-x-auto border rounded-lg">

        </div>
        <!-- progress bar -->
        <div class="flex flex-col rounded py-3 px-3">
            <div class="mb-2 flex gap-2">
            <span class="mb-2 h-[15px] flex-1 rounded-xl bg-black"></span>
            <span class="mb-2 h-[15px] flex-1 rounded-xl bg-black"></span>
            <span class="mb-2 h-[15px] flex-1 rounded-xl bg-black"></span>
            </div>
            <small>langkah terakhir</small>
        </div>

        <div>
            <form wire:submit.prevent="uploadFile">
            <!-- Created By Joker Banny -->
            <div class="flex mt-8">
                <div class="space-y-6 border-l-2 border-dashed">
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">1. Anda memilih Laporan bulan {{ $namaBulan }} tahun {{ $tahun }}</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500"></p>
                    <div class="flex flex-row mt-2">
                        <div class="basis-1/8 mr-3">
                            
                        </div>
                        <div class="basis-1/8 mr-3">
                            
                        </div>
                    </div>
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">2. Upload file performa toko</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500"></p>
                        <!-- Upload Performa Toko -->
                        <div class="mb-4">
                            <label>Upload Performa Toko (XLSX)</label>
                            <input type="file" wire:model="fileToko" accept=".xlsx">
                            @error('fileToko') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">3. Upload file performa iklan</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500"></p>
                        <!-- Upload Performa Iklan -->
                        <div class="mb-4">
                            <label>Upload Performa Iklan (CSV)</label>
                            <input type="file" wire:model="fileIklan" accept=".csv">
                            @error('fileIklan') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="relative w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-0.5 z-10 -ml-3.5 h-7 w-7 rounded-full text-black">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-6">
                    <h4 class="font-bold text-black">Cek kembali, apakah data sudah benar?</h4>
                    <p class="mt-2 max-w-screen-sm text-sm text-gray-500">Klik tombol simpan untuk menyimpan data performa toko Anda.</p>
                    <button type="submit" wire:loading.attr="disabled" class="mt-2 px-4 py-2 bg-black text-white font-bold rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50">Simpan
                    </button>
                    </div>
                </div>
                </div>
            </div>
            </form>
        </div>
    </x-app.container>

    @endvolt
</x-layouts.app>
