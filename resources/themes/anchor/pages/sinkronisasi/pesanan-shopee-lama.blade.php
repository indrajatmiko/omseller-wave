<?php
use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Muhanz\Shoapi\Facades\Shoapi;
use Illuminate\Http\Request;
use App\Models\ShopeeAuth;

middleware('auth');
name('pesanan-shopee-lama');

new class extends Component {
    public $shopData;
    public $showAuthButton = false;

    public $itemData;
    public $orderData;
    public $orderDetail;

    public function mount() {
        $this->loadShopInfo();

        // $this->shopData = $this->get_shop_info();
        // $this->itemData = $this->get_item_list();
        $this->orderData = $this->mock_get_order_list();
        $this->orderDetail = $this->get_order_detail('2504220GWR2Q9A');
    }

    public function loadShopInfo() {
        try {
            $shop = ShopeeAuth::where('user_id', auth()->id())->firstOrFail();
            
            // Auto-refresh token jika diperlukan
            if ($shop->needsTokenRefresh()) {
                app(ShopeeTokenService::class)->refreshAccessToken($shop);
                $shop->refresh();
            }

            $this->shopInfo = $shop->shop_info;
            $this->accessToken = $shop->access_token;
            $this->shopId = $shop->shop_id;

            // Tentukan apakah tombol perlu ditampilkan
            $this->showAuthButton = $shop->isExpired();
        } catch (\Exception $e) {
            $this->error = "Tautkan akun Shopee terlebih dahulu";
        }
    }

    public function isExpired(): bool
    {
        return now()->diffInDays($this->created_at) >= 360;
    }

    public function get_shop_info()
    {
        return Shoapi::call('shop')
            ->access('get_shop_info', $this->accessToken)
            ->shop($this->shopId)
            ->response();
    }

    public function get_order_list($status = null)
    {
        $timeTo = now()->timestamp; // Tanggal hari ini (timestamp)
        $timeFrom = now()->subDays(15)->timestamp; // 15 hari sebelumnya (timestamp)

        $params =  [
            'time_range_field'  =>  'create_time',
            'time_from'  =>  $timeFrom,
            'time_to'  =>  $timeTo,
            'page_size' =>  '20',
            'order_status' =>  $status,
        ];

        return Shoapi::call('order')
            ->access('get_order_list', $this->accessToken)
            ->shop($this->shopId)
            ->request($params)
            ->response();
    }

    public function mock_get_order_list($status = null)
    {
        // --- Gunakan mock response ---
        $mock = [
            "error" => "",
            "message" => "",
            "response" => [
                "more" => true,
                "next_cursor" => "20",
                "order_list" => [
                    ["order_sn" => "201218V2Y6E59M"],
                    ["order_sn" => "201218V2W2SG1E"],
                    ["order_sn" => "201218V2VJJC70"],
                    ["order_sn" => "201218V2TEURPF"],
                    ["order_sn" => "201218UXWNTUNP"],
                    ["order_sn" => "201218UWFYSCF1"],
                    ["order_sn" => "201215MPRFUUNN"],
                    ["order_sn" => "201215MCR3V9N8"],
                    ["order_sn" => "201214JASXYXY6"],
                    ["order_sn" => "201214JAJXU6G7"]
                ]
            ],
            "request_id" => "b937c04e554847789cbf3fe33a0ad5f1"
        ];

        return $mock;
    }

    public function get_order_detail($order_sn = null)
    {
        $params =  [
            'order_sn_list'  =>  $order_sn,
            'response_optional_fields'  =>  'total_amount,buyer_username,recipient_address,item_list',
        ];

        return Shoapi::call('order')
            ->access('get_order_detail', $this->accessToken)
            ->shop($this->shopId)
            ->request($params)
            ->response();
    }
}
?>

<x-layouts.app>
    @volt('pesanan-shopee-lama')
        <x-app.container>
            <div class="flex items-center justify-between mb-4">
                <x-app.heading
                    title="Sinkronisasi Pesanan Shopee"
                    description="Sinkronisasi pesanan dari Shopee ke sistem kami untuk memudahkan pengelolaan data penjualan."
                    :border="true"
                />
                <div class="flex justify-end gap-2">
                    <form method="POST" action="/sinkronisasi/sync-last-year-pesanan-shopee">
                        @csrf
                        <x-button type="submit">Mulai Sinkronisasi</x-button>
                    </form>
                </div>
            </div>

            <div x-data="{
                progress: 0,
                currentPeriod: '',
                orderCount: 0,
                showProgress: false,
                intervalId: null
            }" 
            x-init="
                intervalId = setInterval(() => {
                    fetch('/sync-last-year-progress')
                        .then(res => res.json())
                        .then(data => {
                            progress = data.progress;
                            currentPeriod = data.current_period;
                            orderCount = data.order_count;
                            showProgress = progress < 100;
                            
                            if (progress >= 100) {
                                clearInterval(intervalId);
                                setTimeout(() => {
                                    showProgress = false;
                                    fetch('/clear-sync-last-year-cache');
                                }, 5000);
                            }
                        });
                }, 2000)"
            >
                <!-- Progress Bar -->
                <div x-show="showProgress" class="mb-8">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm font-medium" x-text="currentPeriod"></span>
                        <span class="text-sm" x-text="`${progress.toFixed(1)}%`"></span>
                    </div>
                    <div class="h-4 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 transition-all duration-500" 
                             :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        Total pesanan terkumpul: <span x-text="orderCount"></span>
                    </p>
                </div>
            </div>

            @if(Storage::exists("shopee_orders/".auth()->id().".json"))
                <div class="mt-8">
                    <h2 class="text-xl font-bold mb-4">Data Order SN:</h2>
                    <pre class="bg-gray-100 p-4 rounded">{{ json_encode(json_decode(Storage::get("shopee_orders/".auth()->id().".json")), JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif

            <div class="mt-8">
                <h2 class="text-xl font-bold mb-4">Data Order:</h2>
                <pre class="bg-gray-100 p-4 rounded">{{ json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>

            <div class="mt-8">
                <h2 class="text-xl font-bold mb-4">Data Order SN:</h2>
                <pre class="bg-gray-100 p-4 rounded">{{ json_encode($orderDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>

        </x-app.container>
    @endvolt
</x-layouts.app>