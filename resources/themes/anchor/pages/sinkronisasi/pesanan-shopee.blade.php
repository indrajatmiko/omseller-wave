<?php
use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Muhanz\Shoapi\Facades\Shoapi;
use Illuminate\Http\Request;
use App\Models\ShopeeAuth;

middleware('auth');
name('pesanan-shopee');

new class extends Component {
    public $shopData;
    public $showAuthButton = false;

    public $itemData;
    public $orderData;

    public function mount() {
        $this->loadShopInfo();

        // $this->shopData = $this->get_shop_info();
        // $this->itemData = $this->get_item_list();
        $this->orderData = $this->get_order_list();
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

    public function get_item_list()
    {
        $params =  [
            'offset'  =>  '0',
            'page_size' =>  '10',
            'item_status' =>  ['NORMAL'],
        ];

        return Shoapi::call('product')
            ->access('get_item_list', $this->accessToken)
            ->shop($this->shopId)
            ->request($params)
            ->response();
    }

    public function get_order_list()
    {
        $timeTo = now()->timestamp; // Tanggal hari ini (timestamp)
        $timeFrom = now()->subDays(15)->timestamp; // 15 hari sebelumnya (timestamp)

        $params =  [
            'time_range_field'  =>  'create_time',
            'time_from'  =>  $timeFrom,
            'time_to'  =>  $timeTo,
            'page_size' =>  '20',
            // 'order_status' =>  'READY_TO_SHIP',
        ];

        return Shoapi::call('order')
            ->access('get_order_list', $this->accessToken)
            ->shop($this->shopId)
            ->request($params)
            ->response();
    }
}
?>

<x-layouts.app>
    @volt('pesanan-shopee')
        <x-app.container>
            <div class="flex items-center justify-between mb-4">
                <x-app.heading
                    title="Sinkronisasi Pesanan Shopee"
                    description="Sinkronisasi pesanan dari Shopee ke sistem kami untuk memudahkan pengelolaan data penjualan."
                    :border="true"
                />
                <div class="flex justify-end gap-2">
                    @if($this->showAuthButton)
                        <x-button tag="a" href="/sinkronisasi/auth-shopee">Authentikasi Shopee</x-button>
                    @endif
                </div>
            </div>
            <div>
                Table : {{ $this->shopInfo}}
                <h2>Informasi Item:</h2>
                <pre>{{ json_encode($orderData, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </x-app.container>


    @endvolt
</x-layouts.app>