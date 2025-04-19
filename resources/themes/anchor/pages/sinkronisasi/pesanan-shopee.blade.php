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

    public function mount() {
        $this->loadShopInfo();
        $this->shopData = $this->get_shop_info();
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

        } catch (\Exception $e) {
            $this->error = "Tautkan akun Shopee terlebih dahulu";
        }
    }

    public function get_shop_info()
    {
        return Shoapi::call('shop')
            ->access('get_shop_info', $this->accessToken)
            ->shop($this->shopId)
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
                    <x-button tag="a" href="/sinkronisasi/auth-shopee">Authentikasi Shopee</x-button>
                </div>
            </div>
            <div>
                Table : {{ $this->shopInfo}}
                <h2>Informasi Toko:</h2>
                <pre>{{ json_encode($shopData, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </x-app.container>


    @endvolt
</x-layouts.app>