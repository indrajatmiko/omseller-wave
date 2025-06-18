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
    public $orderDetail;

    public function mount() {
        $this->loadShopInfo();

        $this->shopData = $this->get_shop_info();
        // $this->itemData = $this->get_item_list();
        $this->orderData = $this->mock_get_order_list();
        // $this->orderDetail = $this->get_order_detail('2504220GWR2Q9A,250415C9VP2DB9');
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

    public function mock_get_order_list($order_sn = null)
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

    public function mock_get_order_detail($order_sn = null)
    {
        $mock = [
            "api_status" => "success",
            "order_list" => [
                [
                    "advance_package" => false,
                    "booking_sn" => "",
                    "buyer_username" => "sandbox_buyer.b04796ac2b26bc",
                    "cod" => false,
                    "create_time" => 1745318979,
                    "currency" => "SGD",
                    "days_to_ship" => 3,
                    "item_list" => [
                        [
                            "item_id" => 1909605,
                            "item_name" => "madu",
                            "item_sku" => "BEE",
                            "model_id" => 0,
                            "model_name" => "",
                            "model_sku" => "",
                            "model_quantity_purchased" => 1,
                            "model_original_price" => 125000,
                            "model_discounted_price" => 125000,
                            "wholesale" => false,
                            "weight" => 1,
                            "add_on_deal" => false,
                            "main_item" => false,
                            "add_on_deal_id" => 0,
                            "promotion_type" => "",
                            "promotion_id" => 0,
                            "order_item_id" => 1909605,
                            "promotion_group_id" => 0,
                            "image_info" => [
                                "image_url" => "https://cf.shopee.sg/file/sg-11134207-7r98o-m8lzo6yd31yv27_tn"
                            ],
                            "product_location_id" => [
                                "SGZ"
                            ],
                            "is_prescription_item" => false,
                            "is_b2c_owned_item" => false
                        ],
                        [
                            "item_id" => 1909698,
                            "item_name" => "Babymizu Hand sanitizer",
                            "item_sku" => "",
                            "model_id" => 9763056,
                            "model_name" => "100 ml",
                            "model_sku" => "SANIT-100",
                            "model_quantity_purchased" => 1,
                            "model_original_price" => 2,
                            "model_discounted_price" => 2,
                            "wholesale" => false,
                            "weight" => 0.3,
                            "add_on_deal" => false,
                            "main_item" => false,
                            "add_on_deal_id" => 0,
                            "promotion_type" => "",
                            "promotion_id" => 0,
                            "order_item_id" => 1909698,
                            "promotion_group_id" => 0,
                            "image_info" => [
                                "image_url" => "https://cf.shopee.sg/file/sg-11134207-7r98o-m8spo2osvgl303_tn"
                            ],
                            "product_location_id" => [
                                "SGZ"
                            ],
                            "is_prescription_item" => false,
                            "is_b2c_owned_item" => false
                        ],
                        [
                            "item_id" => 1909698,
                            "item_name" => "Babymizu Hand sanitizer",
                            "item_sku" => "",
                            "model_id" => 9763057,
                            "model_name" => "250 ml",
                            "model_sku" => "SANIT-250",
                            "model_quantity_purchased" => 1,
                            "model_original_price" => 3,
                            "model_discounted_price" => 3,
                            "wholesale" => false,
                            "weight" => 0.3,
                            "add_on_deal" => false,
                            "main_item" => false,
                            "add_on_deal_id" => 0,
                            "promotion_type" => "",
                            "promotion_id" => 0,
                            "order_item_id" => 1909698,
                            "promotion_group_id" => 0,
                            "image_info" => [
                                "image_url" => "https://cf.shopee.sg/file/sg-11134207-7r98o-m8spoo9pak6vad_tn"
                            ],
                            "product_location_id" => [
                                "SGZ"
                            ],
                            "is_prescription_item" => false,
                            "is_b2c_owned_item" => false
                        ]
                    ],
                    "message_to_seller" => "",
                    "order_sn" => "2504220GWR2Q9A",
                    "order_status" => "COMPLETED",
                    "recipient_address" => [
                        "name" => "a*c",
                        "phone" => "******17",
                        "town" => "",
                        "district" => "",
                        "city" => "",
                        "state" => "",
                        "region" => "SG",
                        "zipcode" => "118551",
                        "full_address" => "13******"
                    ],
                    "region" => "SG",
                    "reverse_shipping_fee" => 0,
                    "ship_by_date" => 0,
                    "total_amount" => 170005,
                    "update_time" => 1747708842
                ]
            ]
        ];

        return $mock;
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

                </div>
            </div>
            <div class="mt-8">
                <h2 class="text-xl font-bold mb-4">Data Order:</h2>
                <pre class="bg-gray-100 p-4 rounded">{{ json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
            <div class="mt-8">
                <h2 class="text-xl font-bold mb-4">Order Detail:</h2>
                <pre class="bg-gray-100 p-4 rounded">{{ json_encode($shopData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>