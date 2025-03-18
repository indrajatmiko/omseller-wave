<?php
    use function Laravel\Folio\{middleware, name};
    use App\Models\Project;
    use Livewire\Volt\Component;

    use Filament\Forms\Components\RichEditor;
    use Filament\Forms\Components\TextInput;
    use Filament\Forms\Form;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Pagination\LengthAwarePaginator;

    middleware('auth');
    name('kalkulator-margin-shopee');

    new class extends Component
    {
        public $kalkulator_margin;
        public $harga_modal = 0;
        public $harga_jual = 0;
        public $margin = 0;
        public $keuntungan_rupiah = 0;
        public $gratis_ongkir_xtra = false;
        public $gratis_xtra = false;
        public $gratisOngkirXtraLimited = false;
        public $promo_xtra = false;
        public $promoXtraLimited = false;

        public $selectedKategori = 0;

        public $search = '';
        public $tempSearch = '';
        public $showModal = false;
        public $perPage = 5;
        public $page = 1;
        public $kategoriProduk = [];
        public $tipePenjual = 'non_star'; // Tambahkan property untuk tipe penjual

        public function mount()
        {
            $this->loadKategoriProduk();
        }

        public function updatedTipePenjual()
        {
            $this->selectedKategori = 0;
            $this->loadKategoriProduk();
        }

        private function loadKategoriProduk()
        {
            $filename = $this->tipePenjual === 'mall' 
                ? 'kategori_produk_mall.json' 
                : 'kategori_produk.json';
            
            $path = public_path('data/' . $filename);
            $this->kategoriProduk = json_decode(file_get_contents($path), true);
        }

        // Mengubah data JSON (dengan struktur pengelompokan) menjadi daftar baris
        public function getFlattenedRowsProperty()
        {
            $rows = [];
            foreach ($this->kategoriProduk as $group) {
                foreach ($group['subcategories'] as $sub) {
                    $rows[] = [
                        'main_category' => $group['main_category'],
                        'subcategory'   => $sub['name'],
                        'description'   => $sub['description'],
                    ];
                }
            }
            if (!empty($this->search)) {
                $rows = array_filter($rows, function ($row) {
                    return stripos($row['description'], $this->search) !== false;
                });
            }
            return array_values($rows);
        }

        // Membuat pagination dari daftar baris yang telah difilter
        public function getPaginatedRowsProperty()
        {
            $allRows = $this->flattenedRows;
            $currentPage = $this->page ?: 1;
            $total = count($allRows);
            $perPage = $this->perPage;
            $slice = array_slice($allRows, ($currentPage - 1) * $perPage, $perPage);
            return new LengthAwarePaginator($slice, $total, $perPage, $currentPage, [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]);
        }

        // Method untuk menerapkan pencarian
        public function applySearch()
        {
            $this->search = $this->tempSearch;
            $this->page = 1;
        }

        public function updatingPerPage()
        {
            $this->page = 1;
        }

        public function nextPage()
        {
            $paginator = $this->paginatedRows;
            if ($this->page < $paginator->lastPage()) {
                $this->page++;
            }
        }

        public function previousPage()
        {
            if ($this->page > 1) {
                $this->page--;
            }
        }
        
        public function formatHargaModal()
        {
            // Ambil angka saja
            $numericValue = preg_replace('/\D/', '', $this->harga_modal);
            $numericValue = $numericValue ?: 0;
            // Format menjadi Rupiah
            $this->harga_modal = 'Rp ' . number_format($numericValue, 0, ',', '.');
        }

        public function formatHargaJual()
        {
            $numericValue = preg_replace('/\D/', '', $this->harga_jual);
            $numericValue = $numericValue ?: 0;
            $this->harga_jual = 'Rp ' . number_format($numericValue, 0, ',', '.');
        }

        public function calculateMargin()
        {
            // Konversi format Rupiah ke angka (menghapus semua karakter non-digit)
            $harga_modal = (float) preg_replace('/\D/', '', $this->harga_modal);
            $harga_jual = (float) preg_replace('/\D/', '', $this->harga_jual);

            if ($harga_modal == 0 || $harga_jual == 0) {
                $this->margin = 0;
                $this->keuntungan_rupiah = 0;
                return;
            }

            $persentase_admin = (float) $this->selectedKategori;

            // Hitung potongan biaya admin
            $potongan_admin = $harga_jual * ($persentase_admin / 100);

            // Hitung biaya tambahan
            $biaya_tambahan = 0;
            if ($this->gratis_ongkir_xtra) {
                $gratisOngkirXtra = $harga_jual * 0.04; // 4%
                $this->gratisOngkirXtraLimited = $gratisOngkirXtra > 10000;
                $biaya_tambahan += min($gratisOngkirXtra, 10000);
            } else {
                $this->gratisOngkirXtraLimited = false;
            }

            if ($this->promo_xtra) {
                $promoXtra = $harga_jual * 0.014; // 1.4%
                $this->promoXtraLimited = $promoXtra > 10000;
                $biaya_tambahan += min($promoXtra, 10000);
            } else {
                $this->promoXtraLimited = false;
            }

            // Hitung keuntungan bersih
            $keuntungan_bersih = $harga_jual - $harga_modal - $potongan_admin - $biaya_tambahan;
            
            // Hitung margin berdasarkan harga jual
            if ($harga_jual > 0) {
                $this->margin = ($keuntungan_bersih / $harga_jual) * 100;
            } else {
                $this->margin = 0;
            }

            // Format tampilan
            $this->margin = number_format($this->margin, 2) . ' %';
            $this->keuntungan_rupiah = 'Rp ' . number_format($keuntungan_bersih, 0, ',', '.');
        }

    }
?>

<x-layouts.app>
    @volt('kalkulator-margin-shopee')
        <div> <!-- Elemen root tunggal yang membungkus semua konten -->
            <x-app.container>
                <div class="flex items-center justify-between mb-">
                    <x-app.heading
                            title="Kalkulator Margin Shopee"
                            description="Kalkulator untuk menghitung berapa margin keuntungan dari produk yang akan Anda jual di Marketplace Shopee. Data per 1 Januari 2025"
                            :border="false"
                        />
                </div>

                <form class="space-y-4 mt-6" wire:submit.prevent="calculateMargin">
                    <div class="mb-6">
                        <label class="block mb-2 text-sm font-medium text-gray-700">Tipe Penjual</label>
                        <div class="flex gap-4">
                            <label class="flex items-center space-x-2">
                                <input 
                                    type="radio" 
                                    value="non_star" 
                                    wire:model.live="tipePenjual" 
                                    class="form-radio text-black focus:ring-black"
                                >
                                <span class="text-sm">Penjual Non Star & Star Seller</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input 
                                    type="radio" 
                                    value="mall" 
                                    wire:model.live="tipePenjual" 
                                    class="form-radio text-black focus:ring-black"
                                >
                                <span class="text-sm">Penjual Mall</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex flex-row">
                        <div class="basis-1/3 mr-3">
                            <label for="harga_modal" class="block mb-2 text-sm font-medium text-gray-700">Harga Modal</label>
                            <input
                                type="text"
                                id="harga_modal"
                                wire:model="harga_modal"
                                wire:blur="formatHargaModal"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50">

                            @error('harga_modal') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="basis-1/3 mr-3">
                            <label for="harga_jual" class="block mb-2 text-sm font-medium text-gray-700">Harga Jual</label>
                            <input
                                type="text"
                                id="harga_jual"
                                wire:model="harga_jual"
                                wire:blur="formatHargaJual"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50">
                            @error('harga_jual') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-row">
                        <div class="basis-1/3 mr-3">
                            <label for="kategori-select" class="block mb-2 text-sm font-medium text-gray-700">Kategori Produk</label>
                            <select 
                                id="kategori-select" 
                                wire:model="selectedKategori"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50"
                            >
                                <option value="0">Pilih Kategori Produk</option>
                                @if($tipePenjual === 'non_star')
                                    <option value="8">Kategori A</option>
                                    <option value="7.5">Kategori B</option>
                                    <option value="5.75">Kategori C</option>
                                    <option value="4.25">Kategori D</option>
                                    <option value="2.5">Kategori E</option>
                                @else
                                    <option value="10.2">Kategori A</option>
                                    <option value="9.7">Kategori B</option>
                                    <option value="7.2">Kategori C</option>
                                    <option value="6.2">Kategori D</option>
                                    <option value="5.2">Kategori E</option>
                                    <option value="3.2">Kategori F</option>
                                    <option value="2.5">Kategori G</option>
                                @endif
                            </select>
                        </div>
                        <div class="basis-1/3 mr-3">
                            <!-- Tombol untuk membuka modal -->
                            <label for="kategori-select" class="block mb-2 text-sm font-medium text-gray-700">&nbsp;</label>
                            <button class="px-4 py-2 bg-black text-white rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50" wire:click="$set('showModal', true)">
                                Lihat Kategori Produk
                            </button>
                        </div>
                    </div>
                    <div>
                        <!-- Modal Popup dengan background blur -->
                        @if ($showModal)
                            <div class="fixed inset-0 flex items-center justify-center bg-gray-700 bg-opacity-50 backdrop-blur-sm">
                                <div class="bg-white p-6 rounded-lg shadow-lg">
                                    <div class="flex justify-between items-center mb-4">
                                        <h2 class="text-lg font-bold">
                                            @if($tipePenjual === 'mall')
                                                Rincian Kategori Produk Penjual Mall
                                            @else
                                                Rincian Kategori Produk Penjual Non-Star dan Star/Star+
                                            @endif
                                        </h2>
                                        <button class="px-4 py-2 bg-red-600 text-white rounded" wire:click="$set('showModal', false)">
                                            Tutup
                                        </button>
                                    </div>
                                    
                                    <!-- Input pencarian dan tombol Cari -->
                                    <div class="flex mb-4">
                                        <input type="text" class="w-full p-2 border rounded" placeholder="Cari kategori produk..." wire:model.defer="tempSearch">
                                        <button class="ml-2 px-4 py-2 bg-green-600 text-white rounded" wire:click="applySearch">
                                            Cari
                                        </button>
                                    </div>
                    
                                    {{-- <!-- Dropdown untuk memilih jumlah data per halaman -->
                                    <div class="mb-4">
                                        <label for="perPage" class="mr-2">Tampilkan per halaman:</label>
                                        <select id="perPage" class="p-2 border rounded" wire:model="perPage">
                                            <option value="3">3</option>
                                            <option value="5">5</option>
                                            <option value="10">10</option>
                                        </select>
                                    </div> --}}
                    
                                    <!-- Tabel dengan 3 kolom: Kategori, Sub Kategori, Deskripsi -->
                                    <table class="w-full border-collapse border border-gray-300">
                                        <thead class="bg-gray-200">
                                            <tr>
                                                {{-- <th class="border px-4 py-2">Kategori</th> --}}
                                                <th class="border px-4 py-2">Kategori</th>
                                                <th class="border px-4 py-2">Rincian</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->paginatedRows as $row)
                                                <tr class="hover:bg-gray-100">
                                                    {{-- <td class="border px-4 py-2">{{ $row['main_category'] }}</td> --}}
                                                    <td class="border px-4 py-2">{{ $row['main_category'] }}</td>
                                                    <td class="border px-4 py-2">{{ $row['subcategory'] }} : {{ \Illuminate\Support\Str::limit($row['description'], 100) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                    
                                    <!-- Kontrol Pagination -->
                                    <div class="flex justify-between items-center mt-4">
                                        <button class="px-4 py-2 bg-gray-600 text-black rounded" wire:click="previousPage" @if($page <= 1) disabled @endif>
                                            Previous
                                        </button>
                                        <span>Halaman {{ $page }} dari {{ $this->paginatedRows->lastPage() }}</span>
                                        <button class="px-4 py-2 bg-gray-600 text-black rounded" wire:click="nextPage" @if($page >= $this->paginatedRows->lastPage()) disabled @endif>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    <div>
                        <div class="flex items-center mb-4">
                            <input id="gratis_ongkir_xtra" type="checkbox" wire:model="gratis_ongkir_xtra" class="w-4 h-4 text-black bg-gray-100 border-gray-300 rounded-sm focus:ring-black focus:ring-offset-gray-800 focus:ring-2">
                            <label for="gratis_ongkir_xtra" class="ml-2 text-sm font-medium text-gray-900">Gratis Ongkir Xtra</label>
                        </div>
                        @if($gratisOngkirXtraLimited)
                        <div class="text-sm text-blue-600 mt-1 ml-6">
                            Potongan Gratis Ongkir Xtra dibatasi maksimal Rp 10.000
                        </div>
                        @endif
                    </div>
                    
                    <div>
                        <div class="flex items-center mb-4">
                            <input id="promo_xtra" type="checkbox" wire:model="promo_xtra" class="w-4 h-4 text-black bg-gray-100 border-gray-300 rounded-sm focus:ring-black focus:ring-offset-gray-800 focus:ring-2">
                            <label for="promo_xtra" class="ml-2 text-sm font-medium text-gray-900">Promo Xtra</label>
                        </div>
                        @if($promoXtraLimited)
                        <div class="text-sm text-blue-600 mt-1 ml-6">
                            Potongan Promo Xtra dibatasi maksimal Rp 10.000
                        </div>
                        @endif
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-black text-white rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50">
                            Hitung Margin
                        </button>
                    </div>
                    <div class="flex flex-row mt-4">
                        <div class="basis-1/3 mr-3">
                            <label for="margin" class="block mb-2 text-sm font-medium text-gray-700">Persentase Keuntungan</label>
                            <input type="text" id="margin" wire:model="margin" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50" readonly>
                            @error('margin') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="basis-1/3 mr-3">
                            <label for="keuntungan_rupiah" class="block mb-2 text-sm font-medium text-gray-700">Keuntungan</label>
                            <input type="text" id="keuntungan_rupiah" wire:model="keuntungan_rupiah" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50" readonly>
                        </div>
                    </div>
                    <div class="text-sm italic text-black mt-1 ml-6">
                        <strong><u>Catatan:</u></strong>
                        <ol>
                            <li>Belum termasuk biaya operasional toko.</li>
                            <li>Shopee berhak sewaktu-waktu mengubah, menambah, atau memodifikasi Syarat & Ketentuan tanpa pemberitahuan terlebih dahulu.</li>
                        </ol>
                    </div>
                </form>
            </x-app.container>
        </div> <!-- Akhir dari elemen root tunggal -->
    @endvolt
</x-layouts.app>