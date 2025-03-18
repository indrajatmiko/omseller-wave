<?php
    use function Laravel\Folio\{middleware, name};
    use App\Models\Project;
    use Livewire\Volt\Component;

    use Filament\Forms\Components\RichEditor;
    use Filament\Forms\Components\TextInput;
    use Filament\Forms\Form;
    use Illuminate\Support\Facades\Storage;

    middleware('auth');
    name('kalkulator-margin-tiktok');

    new class extends Component
    {
        public $kalkulator_margin;
        public $harga_modal;
        public $harga_jual;
        public $margin;

        public $kategoriProduk = [];
        public $selectedKategori = 0;

        public function mount()
        {
            // Ambil data dari file JSON
            $path = public_path('data/kategori_produk.json');
            $this->kategoriProduk = json_decode(file_get_contents($path), true);
        }
    }
?>

<x-layouts.app>
    @volt('kalkulator-margin-tiktok')
        <x-app.container>
            <div class="flex items-center justify-between mb-">
                <x-app.heading
                        title="Kalkulator Margin Tiktok"
                        description="Kalkulator untuk menghitung berapa margin keuntungan dari produk yang akan Anda jual di Marketplace Tiktok"
                        :border="false"
                    />
            </div>

            <form class="space-y-4 mt-6">
                <div class="flex flex-row">
                    <div class="basis-1/3 mr-3">
                        <label for="harga_modal" class="block mb-2 text-sm font-medium text-gray-700">Harga Modal</label>
                        <input type="text" id="harga_modal" wire:model="harga_modal" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50">
                        @error('harga_modal') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="basis-1/3 mr-3">
                        <label for="harga_jual" class="block mb-2 text-sm font-medium text-gray-700">Harga Jual</label>
                        <input type="text" id="harga_jual" wire:model="harga_jual" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50">
                        @error('harga_jual') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div>
                    <label for="biaya_admin" class="block mb-2 text-sm font-medium text-gray-700">Kategori Produk</label>
                    <select 
                        id="biaya_admin" 
                        name="biaya_admin" 
                        wire:model="selectedKategori" 
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50 select2"
                    >
                        <option value="0">Pilih Kategori Produk</option>
                        @foreach ($kategoriProduk as $kategori)
                            <option value="{{ $kategori['biaya_admin'] }}">{{ $kategori['nama'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flex items-center mb-4">
                        <input id="gratis_ongkir_xtra" type="checkbox" name="gratis_ongkir_xtra" value="4" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                        <label for="gratis_ongkir_xtra" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Gratis Ongkir Xtra</label>
                    </div>
                </div>
                <div>
                    <div class="flex items-center mb-4">
                        <input id="promo_xtra" type="checkbox" id="promo_xtra" name="promo_xtra" value="1.4" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                        <label for="promo_xtra" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Promo Xtra</label>
                    </div>
                </div>
                <div class="flex flex-row">
                    <div class="basis-1/3 mr-3">
                        <label for="margin" class="block mb-2 text-sm font-medium text-gray-700">Persentase Keuntungan</label>
                        <input type="text" id="margin" wire:model="margin" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50" readonly>
                        @error('margin') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="basis-1/3 mr-3">
                        <label for="keuntungan_rupiah" class="block mb-2 text-sm font-medium text-gray-700">Keuntungan</label>
                        <input type="text" id="keuntungan_rupiah" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-opacity-50" readonly>
                    </div>
                </div>
            </form>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // $('.select2').select2();
                
                    const hargaModal = document.getElementById('harga_modal');
                    const hargaJual = document.getElementById('harga_jual');
                    const margin = document.getElementById('margin');
                    const keuntunganRupiah = document.getElementById('keuntungan_rupiah');
                    const biayaAdmin = document.getElementById('biaya_admin');
                    const gratisOngkirXtra = document.getElementById('gratis_ongkir_xtra');
                    const promoXtra = document.getElementById('promo_xtra');

                    // Fungsi untuk memformat angka ke format Rupiah
                    function formatRupiah(angka) {
                        let number_string = angka.replace(/[^,\d]/g, '').toString();
                        let split = number_string.split(',');
                        let sisa = split[0].length % 3;
                        let rupiah = split[0].substr(0, sisa);
                        let ribuan = split[0].substr(sisa).match(/\d{3}/g);

                        if (ribuan) {
                            let separator = sisa ? '.' : '';
                            rupiah += separator + ribuan.join('.');
                        }

                        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
                        return rupiah ? 'Rp ' + rupiah : '';
                    }

                    // Fungsi untuk menghapus format Rupiah
                    function unformatRupiah(rupiah) {
                        return parseInt(rupiah.replace(/[^0-9]/g, ''), 10);
                    }

                    // Format harga_modal saat diketik
                    hargaModal.addEventListener('input', function() {
                        this.value = formatRupiah(this.value);
                        calculateMargin();
                    });

                    // Format harga_jual saat diketik
                    hargaJual.addEventListener('input', function() {
                        this.value = formatRupiah(this.value);
                        calculateMargin();
                    });

                    // Fungsi untuk menghitung margin
                    function calculateMargin() {
                        const modal = unformatRupiah(hargaModal.value) || 0;
                        const jual = unformatRupiah(hargaJual.value) || 0;
                        const admin = parseFloat(biayaAdmin.value) || 0;
                        const ongkir = gratisOngkirXtra.checked ? parseFloat(gratisOngkirXtra.value) : 0;
                        const promo = promoXtra.checked ? parseFloat(promoXtra.value) : 0;

                        if (modal && jual) {
                            const profit = jual - modal;
                            const marginValue = (profit / jual) * 100;
                            const totalMargin = marginValue - admin - ongkir - promo;
                            margin.value = totalMargin.toFixed(2) + '%';

                            // Hitung keuntungan dalam rupiah
                            const keuntungan = profit - (jual * (admin + ongkir + promo) / 100);
                            keuntunganRupiah.value = `Rp ${keuntungan.toLocaleString('id-ID')}`;
                        } else {
                            margin.value = '';
                            keuntunganRupiah.value = '';
                        }
                    }

                    // Event listener untuk elemen lain
                    // biayaAdmin.addEventListener('change', calculateMargin);
                    gratisOngkirXtra.addEventListener('change', calculateMargin);
                    promoXtra.addEventListener('change', calculateMargin);
                    document.querySelector("#biaya_admin").onchange = element => {
                        calculateMargin();
                    }
                });
                </script>
        </x-app.container>
    @endvolt
</x-layouts.app>