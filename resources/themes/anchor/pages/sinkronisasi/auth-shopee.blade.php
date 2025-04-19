<?php
    use function Laravel\Folio\{middleware, name};
    use Livewire\Volt\Component;
    use App\Models\ShopeeAuth;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Http\Request;

    middleware('auth');
    name('auth-shopee');

    new class extends Component {
        public $auth_url;
        public $shopInfo = null;
        public $error = null;
        public $isLoading = false;
        public $code;
        public $shop_id;

        public function mount(Request $request) {
            $this->code = $request->query('code');
            $this->shop_id = $request->query('shop_id');

            $this->auth_url = Shoapi::call('shop')->access('auth_partner')->getUrl();
            $this->handleAuthorizationCallback();
        }

        private function handleAuthorizationCallback() {
            if (!$this->code || !$this->shop_id) return;

            $this->isLoading = true;

            try {
                $params = [
                    'code' => $this->code,
                    'shop_id' => (int) $this->shop_id,
                ];

                $resp = Shoapi::call('auth')
                    ->access('get_access_token')
                    ->shop($params['shop_id'])
                    ->request($params)
                    ->response();

                $response = json_decode(json_encode($resp), true);
                Log::debug('API', [
                    'response' => $response,
                    'params' => $params,
                ]);

                if ($response['api_status'] == 'success') {
                    $this->storeTokens($response);
                    $this->loadShopInfo($this->shop_id);

                    return redirect('/sinkronisasi/pesanan-shopee');
                } else {
                    throw new \Exception($response['message'] ?? 'Unknown error.');
                }
            } catch (\Exception $e) {
                $this->error = "Gagal mendapatkan token: " . $e->getMessage();
            }

            $this->isLoading = false;
        }

        private function storeTokens($response) {
            DB::transaction(function () use ($response) {
                ShopeeAuth::updateOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'shop_id' => $this->shop_id
                    ],
                    [
                        'access_token' => $response['access_token'],
                        'refresh_token' => $response['refresh_token'],
                        'expires_at' => now()->addSeconds($response['expire_in']),
                        'shop_info' => null
                    ]
                );
            });
        }

        public function loadShopInfo($shopId = null) {
            try {
                $shop = ShopeeAuth::where('user_id', auth()->id())
                    ->where('shop_id', $shopId)
                    ->firstOrFail();

                if (now()->gt($shop->expires_at)) {
                    $this->refreshToken($shop);
                    $shop->refresh(); // Ambil data terbaru
                }

                if (!$shop->shop_info) {
                    $shopInfo = Shoapi::call('shop')
                        ->access('get_shop_info', $shop->access_token)
                        ->shop($shop->shop_id)
                        ->response();

                    $response_shop  = json_decode(json_encode($shopInfo), true);
                    $shop->update(['shop_info' => $response_shop['shop_name']]);
                }

                $this->shopInfo = $shop->shop_info;

            } catch (\Exception $e) {
                $this->error = "Gagal memuat data toko: " . $e->getMessage();
            }
        }

        private function refreshToken($shop) {
            $response = Shoapi::call('auth')
                ->access('refresh_access_token')
                ->shop($shop->shop_id)
                ->request([
                    'refresh_token' => $shop->refresh_token,
                    'shop_id' => $shop->shop_id,
                ])->response();

            if ($response['api_status'] === 'success') {
                $shop->update([
                    'access_token' => $response['access_token'],
                    'refresh_token' => $response['refresh_token'],
                    'expires_at' => now()->addSeconds($response['expire_in'])
                ]);
            }
        }
    }
?>

<x-layouts.app>
    @volt('auth-shopee')
        <x-app.container>
            <div class="flex items-center justify-between mb-4">
                <x-app.heading
                    title="Sinkronisasi Pesanan Shopee"
                    description="Sinkronisasi pesanan dari Shopee ke sistem kami untuk memudahkan pengelolaan data penjualan."
                    :border="true"
                />
            </div>
            @if($isLoading)
                <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                    <div role="status">
                            <svg
                                aria-hidden="true"
                                class="w-20 h-20 text-gray-200 animate-spin dark:text-gray-600 fill-blue-500 dark:fill-blue-50"
                                viewBox="0 0 100 101"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                                fill="currentColor"
                            />
                            <path
                                d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                                fill="currentFill"
                            />
                        </svg>
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            @elseif(!$shopInfo)
                <div class="space-y-4">
                    <x-button 
                        tag="a" 
                        href="{{ $auth_url }}" 
                        class="gap-2"
                    >
                        Authentikasi Shopee
                    </x-button>
                </div>
            @else
                <div class="bg-white rounded-lg shadow p-6 animate-fade-in">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-bold">{{ $shopInfo['shop_name'] ?? 'Nama Toko' }}</h3>
                        <div class="space-x-2">
                            <x-button 
                                wire:click="loadShopInfo({{ $shopInfo['shop_id'] ?? '' }})"
                                spinner
                            >
                                Refresh Data
                            </x-button>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        @php
                            // dd($shopInfo);
                            $shopInfo = json_decode($shopInfo['shop_info'], true);
                        @endphp
                        {{-- <p>📍 Lokasi: {{ $shopInfo['region'] }}</p>
                        <p>⭐ Rating: {{ $shopInfo['rating_star'] }}</p>
                        <p>🛍️ Total Produk: {{ $shopInfo['item_count'] }}</p> --}}
                    </div>
                </div>
            @endif
        </x-app.container>
    @endvolt
</x-layouts.app>