<?php
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Illuminate\Http\Request;

    middleware('auth');
    name('datacenter-organik-iklan.ai-analysis-blended-acos');

    new class extends Component {
        public $totalAcos;
        public $analysisMessages = [];

        public function mount(Request $request)
        {
            $this->totalAcos = (int) $request->query('totalAcos', 0);
            $this->buildAnalysisMessages();
        }

        protected function buildAnalysisMessages()
        {
            $this->analysisMessages = [
                'Okay, mari kita bahas Blended ACOS dan saya akan memberikan analisis dan saran berdasarkan data yang Anda berikan.',
                'Apa itu Blended ACOS? Blended ACOS (Advertising Cost of Sales) adalah metrik yang mengukur persentase total biaya iklan Anda terhadap total pendapatan penjualan toko Anda dalam periode waktu tertentu.
                ',
                'Berbeda dengan ACOS biasa (yang hanya melihat penjualan dari iklan tertentu), Blended ACOS memberikan gambaran yang lebih holistik (menyeluruh) tentang kesehatan bisnis Anda terkait efektivitas iklan:',
                '1. Efisiensi Pengeluaran Iklan secara Keseluruhan: Blended ACOS menunjukkan berapa banyak Rupiah yang Anda keluarkan untuk iklan untuk menghasilkan setiap Rp 100 penjualan di seluruh toko Anda (termasuk penjualan organik/tidak langsung dari iklan).',
                '2. Dampak Iklan pada Profitabilitas Total: Metrik ini secara langsung berkaitan dengan margin keuntungan keseluruhan Anda. Semakin rendah Blended ACOS (dengan asumsi penjualan stabil atau meningkat), semakin besar potensi keuntungan bersih Anda karena biaya akuisisi pelanggan melalui iklan relatif rendah terhadap total pendapatan.',
                '3. Mengukur Efek "Halo" Iklan: Iklan tidak hanya menghasilkan penjualan langsung. Iklan juga meningkatkan brand awareness (kesadaran merek) dan dapat mendorong penjualan organik (orang mencari langsung toko Anda atau membeli nanti). Blended ACOS membantu menangkap sebagian dari dampak tidak langsung ini.',
                '4. Indikator Kesehatan Bisnis Jangka Panjang: Memantau tren Blended ACOS dari waktu ke waktu (seperti data bulanan Anda) membantu Anda memahami apakah strategi iklan Anda secara keseluruhan berkelanjutan dan berkontribusi positif pada pertumbuhan bisnis total, bukan hanya pada penjualan yang diatribusikan ke iklan.',
                '5. Pengambilan Keputusan Strategis:<br>
                    Blended ACOS Rendah: Ini biasanya sangat bagus! Menandakan iklan Anda sangat efisien dalam mendorong penjualan keseluruhan. Anda mungkin memiliki ruang untuk meningkatkan budget iklan secara hati-hati untuk mendorong pertumbuhan lebih lanjut, atau fokus pada optimalisasi lain.
                    <br>
                    Blended ACOS Tinggi: Ini bisa berarti biaya iklan Anda "memakan" terlalu banyak dari total pendapatan Anda, yang berpotensi menekan profitabilitas. Perlu evaluasi apakah target pasar sudah tepat, materi iklan efektif, atau mungkin margin produk perlu ditinjau.',
                '<strong>Singkatnya:</strong> Blended ACOS adalah termometer yang mengukur seberapa efisien seluruh mesin periklanan Anda bekerja dalam konteks seluruh pendapatan toko Anda. Ini penting untuk memastikan iklan tidak hanya menghasilkan klik atau penjualan langsung, tetapi benar-benar mendukung kesehatan finansial dan pertumbuhan bisnis e-commerce Anda secara keseluruhan.',
                'Saya tegaskan bahwa informasi ini bersifat umum dan perlu disesuaikan dengan margin keuntungan spesifik toko Anda, industri, dan tujuan bisnis (misalnya, fokus pada profitabilitas vs. pertumbuhan pangsa pasar).',
                'Berdasarkan informasi sebelumnya, <strong>Blended ACOS toko Anda adalah ' . $this->totalAcos . '%.</strong>, berikut adalah analisis dan saran untuk Anda:',
                'Dalam kasus ini: Angka ' . $this->totalAcos . '% berarti bahwa untuk setiap Rp 100 total penjualan yang dihasilkan oleh toko Anda (baik dari iklan maupun non-iklan/organik), Rp ' . $this->totalAcos . ' di antaranya dihabiskan untuk biaya iklan. Saat ini Anda berada di posisi :'
            ];

            if ($this->totalAcos <= 10) {
                $this->analysisMessages[] = "<strong>0% - 10% (Sangat Baik / Excellent):</strong><br><strong>Kondisi:</strong> Biaya iklan sangat rendah dibandingkan total penjualan. Efisiensi iklan sangat tinggi.<br><strong>Saran:</strong> Performa iklan Anda luar biasa efisien! Pertahankan strategi yang berjalan baik. Pertimbangkan alokasi budget iklan sedikit lebih tinggi secara bertahap untuk tes skala pertumbuhan penjualan, sambil terus memantau Blended ACOS.";
            } 
            elseif ($this->totalAcos > 10 && $this->totalAcos <= 20) {
                $this->analysisMessages[] = "<strong>10% - 20% (Baik / Good):</strong><br><strong>Kondisi:</strong> Biaya iklan masih dalam proporsi yang sehat terhadap total penjualan untuk banyak bisnis.<br><strong>Saran:</strong> Blended ACOS Anda berada di level yang baik. Iklan berkontribusi positif pada penjualan total. Lakukan analisis kampanye mana yang paling efisien dan fokuskan optimasi di sana. Pantau terus agar tidak melebihi batas profitabilitas Anda";
            } 
            elseif ($this->totalAcos > 20 && $this->totalAcos <= 30) {
                $this->analysisMessages[] = "<strong>20% - 30% (Cukup / Fair / Perlu Perhatian):</strong><br><strong>Kondisi:</strong> Biaya iklan mulai signifikan terhadap total penjualan. Profitabilitas perlu dipantau ketat. Mungkin wajar jika sedang agresif mengejar pertumbuhan atau menjual produk margin rendah.<br><strong>Saran:</strong> Blended ACOS Anda cukup tinggi. Pastikan margin keuntungan Anda masih sehat setelah dikurangi biaya iklan ini. Tinjau efektivitas kampanye iklan, target audiens, dan kata kunci. Pertimbangkan optimasi landing page atau penawaran produk.";
            }  
            elseif ($this->totalAcos > 30 && $this->totalAcos <= 50) {
                $this->analysisMessages[] = "<strong>30% - 50% (Tinggi / High / Waspada):</strong><br><strong>Kondisi:</strong> Biaya iklan mengambil porsi besar dari total pendapatan. Berisiko tinggi menggerus profitabilitas, kecuali jika memiliki margin produk sangat tinggi atau strategi jangka panjang yang jelas (misal: peluncuran besar).<br><strong>Saran:</strong> Perhatian! Blended ACOS Anda tinggi. Lakukan audit mendalam pada semua kampanye iklan. Identifikasi pemborosan budget. Fokus pada kampanye dengan ROI tertinggi. Pertimbangkan untuk mengurangi budget pada kampanye yang kurang perform atau re-evaluasi strategi penetapan harga/margin produk.";
            } 
            else {
                $this->analysisMessages[] = "<strong>> 50% (Sangat Tinggi / Very High / Kritis):</strong><br><strong>Kondisi:</strong> Biaya iklan melebihi separuh dari total pendapatan. Umumnya tidak berkelanjutan kecuali dalam skenario yang sangat spesifik dan sementara. Sangat mungkin bisnis merugi secara keseluruhan karena iklan.<br><strong>Saran:</strong> Kritis! Blended ACOS sangat tinggi dan berpotensi besar merugikan bisnis Anda. Segera hentikan kampanye yang tidak profitabel. Lakukan evaluasi total strategi marketing dan struktur biaya Anda. Mungkin perlu bantuan ahli untuk merestrukturisasi iklan atau model bisnis.";
            }

            $this->analysisMessages[] = "<strong>Penting:</strong> Blended ACOS (ideal) sangat bergantung pada Break-Even Point Anda. Jika margin keuntungan kotor Anda (sebelum biaya iklan) adalah 40%, maka Blended ACOS di atas 40% secara teori membuat bisnis Anda rugi dari segi operasional keseluruhan (meskipun beberapa penjualan individu dari iklan mungkin profitabel).";
            $this->analysisMessages[] = "<i>Data ini hanya sebagai panduan awal. Untuk analisis yang lebih mendalam, Anda mungkin harus mempertimbangkan faktor-faktor lain seperti margin keuntungan, biaya akuisisi pelanggan (CAC), dan biaya lainnya.</i>";

        }
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan.ai-analysis-blended-acos')
    <x-app.container>
        {{-- <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-b from-gray-900 to-gray-800 p-6"> --}}
            <div class="flex items-center justify-between mb-5">
                <x-app.heading title="Analisa AI" description="Menggunakan model AI Gemini 2.5 Pro" :border="false" />
            </div>

            <!-- Chat Container -->
            <div class="rounded-lg shadow-xl p-6 max-w-3xl w-full">
                <!-- Chat Messages -->
                <div 
                    class="space-y-4"
                    x-data="{
                        text: '',
                        index: 0,
                        charIndex: 0,
                        messages: @js($analysisMessages),
                        visibleMessages: [],
                        typing() {
                            if (this.index < this.messages.length) {
                                if (this.charIndex < this.messages[this.index].length) {
                                    this.text += this.messages[this.index][this.charIndex];
                                    this.charIndex++;
                                } else {
                                    this.visibleMessages.push(this.text);
                                    this.text = '';
                                    this.charIndex = 0;
                                    this.index++;
                                }
                                setTimeout(() => this.typing(), 10);
                            }
                        }
                    }"
                    x-init="typing()"
                >
                    <!-- User Chat Bubble -->
                    <div class="flex justify-end">
                        <div class="bg-gray-200 p-4 rounded-lg shadow-md max-w-lg">
                            <p class="text-sm text-black leading-relaxed">
                                Jelaskan apa itu ACOS Blended? berikan analisis dan saran untuk ACOS Blended toko saya yang berada di <strong>{{ $totalAcos }}%</strong>, berikan juga kesimpulan dan saran untuk saya.
                            </p>
                        </div>
                    </div>

                    <!-- AI Chat Bubble -->
                    <template x-for="message in visibleMessages" :key="message">
                        <div class="flex items-start">
                            <div class="bg-gray-700 p-4 rounded-lg shadow-md max-w-lg">
                                <p x-html="message" class="text-sm text-white leading-relaxed"></p>
                            </div>
                        </div>
                    </template>

                    <!-- Typing Indicator -->
                    <div x-show="text !== ''" class="flex items-start">
                        <div class="bg-gray-700 text-gray-200 p-4 rounded-lg shadow-md max-w-lg">
                            <p x-html="text" class="text-sm leading-relaxed"></p>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="mt-8 flex justify-center">
                    <button 
                        class="mt-2 px-4 py-2 bg-black text-white font-bold rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-opacity-50"
                        onclick="window.history.back()"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Kembali
                    </button>
                </div>
            </div>
        {{-- </div> --}}
    </x-app.container>
    @endvolt
</x-layouts.app>