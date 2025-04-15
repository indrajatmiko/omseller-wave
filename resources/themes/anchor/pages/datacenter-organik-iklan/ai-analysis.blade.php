<?php
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Illuminate\Http\Request;

    middleware('auth');
    name('datacenter-organik-iklan.ai-analysis');

    new class extends Component {
        public $persenIklanYear;
        public $analysisMessages = [];

        public function mount(Request $request)
        {
            $this->persenIklanYear = (int) $request->query('persenIklanYear', 0);
            $this->buildAnalysisMessages();
        }

        protected function buildAnalysisMessages()
        {
            $this->analysisMessages = [
                'Oke, Mari kita analisa komposisi penjualan yang berasal dari iklan dibandingkan dengan total penjualan dalam skala e-commerce.',
                '<strong>Persentase Iklan toko Anda adalah ' . $this->persenIklanYear . '%.</strong>',
                'Tidak ada angka ajaib tunggal yang berlaku untuk semua bisnis, karena sangat tergantung pada:
                <br>- Tahap Bisnis: Bisnis baru mungkin memerlukan persentase iklan yang lebih tinggi untuk membangun kesadaran awal.
                <br>- Industri & Niche: Tingkat persaingan dan perilaku konsumen di niche Anda akan berpengaruh.
                <br>- Margin Produk: Produk dengan margin tinggi dapat mentolerir ketergantungan iklan yang sedikit lebih tinggi.
                <br>- Tujuan Bisnis: Apakah fokus utama Anda saat ini adalah pertumbuhan agresif atau profitabilitas?
                ',
                'Berdasarkan Range Komposisi Iklan vs Total Penjualan yang Dianggap Baik (Saran Investasi secara umum), toko Anda berada pada kategori:',
            ];

            if ($this->persenIklanYear >= 20 && $this->persenIklanYear <= 40) {
                $this->analysisMessages[] = "<strong>Ideal / Sangat Sehat (20% - 40%):</strong><br><strong>Analisa:</strong> Ini sering dianggap sebagai sweet spot untuk bisnis yang sudah berjalan dan memiliki fondasi yang baik. Menunjukkan bahwa iklan Anda efektif dalam mendorong penjualan, tetapi tidak menjadi satu-satunya tumpuan.<br><strong>Investasi: </strong>Menandakan investasi yang seimbang antara akuisisi pelanggan baru melalui iklan dan upaya membangun brand serta loyalitas pelanggan melalui channel organik dan retensi.";
            } 
            elseif ($this->persenIklanYear > 40 && $this->persenIklanYear <= 60) {
                $this->analysisMessages[] = "<strong>Agresif / Fase Pertumbuhan (40% - 60%):</strong><br><strong>Analisa:</strong> Range ini bisa diterima, terutama untuk bisnis yang sedang dalam fase pertumbuhan cepat, meluncurkan produk baru, atau memasuki pasar yang sangat kompetitif.<br><strong>Investasi:</strong> Menunjukkan fokus investasi yang lebih berat pada akuisisi melalui iklan. Penting untuk secara paralel mulai menginvestasikan sumber daya untuk memperkuat channel organik dan strategi retensi agar persentase ini dapat diturunkan seiring waktu.";
            } 
            elseif ($this->persenIklanYear > 60) {
                $this->analysisMessages[] = "<strong>Perlu Perhatian / Risiko Tinggi (> 60%):</strong><br><strong>Analisa:</strong> Ini menunjukkan ketergantungan yang sangat tinggi pada iklan. Risiko terhadap profitabilitas dan keberlanjutan jangka panjang meningkat secara signifikan.<br><strong>Investasi:</strong> Mungkin investasi saat ini terlalu berat sebelah ke arah iklan berbayar. Perlu ada realokasi sumber daya atau investasi tambahan yang signifikan ke SEO, konten, email marketing, community building, dan program loyalitas.";
            } 
            else {
                $this->analysisMessages[] = "<strong>Tidak Sehat (< 20%):</strong><br><strong>Analisa:</strong> Ini menunjukkan bahwa iklan tidak berfungsi dengan baik atau Anda memiliki masalah yang lebih besar dalam strategi pemasaran Anda.<br><strong>Investasi:</strong> Perlu evaluasi menyeluruh terhadap strategi iklan dan pemasaran secara keseluruhan. Fokus pada perbaikan produk, pengalaman pelanggan, dan membangun brand awareness.";
            }

            $this->analysisMessages[] = "<strong>Kesimpulan dan Saran:</strong>\nTujuan utama bukanlah menghilangkan iklan sama sekali, tetapi menciptakan keseimbangan yang sehat antara penjualan dari iklan dan penjualan dari sumber organik/repeat customer.";
            $this->analysisMessages[] = "Dengan mencapai keseimbangan yang lebih baik, Anda membangun bisnis e-commerce yang tidak hanya tumbuh tetapi juga lebih tangguh, menguntungkan, dan berkelanjutan dalam jangka panjang.";
            $this->analysisMessages[] = "<i>Data ini hanya sebagai panduan awal. Untuk analisis yang lebih mendalam, Anda mungkin harus mempertimbangkan faktor-faktor lain seperti margin keuntungan, biaya akuisisi pelanggan (CAC), dan biaya lainnya.</i>";

        }
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan.ai-analysis')
    <x-app.container>
        {{-- <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-b from-gray-900 to-gray-800 p-6"> --}}
            <div class="flex items-center justify-between mb-5">
                <x-app.heading title="Analisa AI" description="Hasil analisa ini menggunakan model AI Gemini 2.5 Pro." :border="false" />
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
                                Analisa komposisi iklan dibanding dengan total penjualan dari toko e-commerce saya, dengan persentase iklan sebesar <strong>{{ $persenIklanYear }}%</strong>, berikan juga kesimpulan dan saran untuk saya.
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