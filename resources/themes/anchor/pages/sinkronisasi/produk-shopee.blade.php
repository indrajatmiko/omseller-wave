<?php
use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\{Cache, Log};
use Muhanz\Shoapi\Facades\Shoapi;
use App\Models\{ShopeeAuth, Sku, SkuModel};

middleware('auth');
name('produk-shopee');

new class extends Component {
    use WithPagination;

    public $accessToken;
    public $shopId;

    public $harga_modal = [];
    public $harga_modal_model = [];

    public function mount() {
        // $this->loadShopInfo();
        
        // Optimasi query harga modal
        $this->harga_modal = Sku::where('user_id', auth()->id())
            ->get(['id', 'harga_modal'])
            ->pluck('harga_modal', 'id')
            ->toArray();

        $this->harga_modal_model = SkuModel::whereIn('sku_id', array_keys($this->harga_modal))
            ->get(['id', 'harga_modal'])
            ->pluck('harga_modal', 'id')
            ->toArray();
    }

    public function getSkusProperty() {
        return Sku::with(['models' => fn($q) => $q->select('id', 'sku_id', 'harga_modal', 'model_sku', 'image_url', 'variation_option_name', 'original_price', 'current_price')])
            ->where('user_id', auth()->id())
            ->select('id', 'item_name', 'item_sku', 'image_url', 'original_price', 'current_price', 'has_model', 'original_brand_name', 'item_status', 'harga_modal')
            ->paginate(10);
    }
    
    public function loadShopInfo() {
        try {
            $shop = ShopeeAuth::where('user_id', auth()->id())
                ->select('shop_info', 'access_token', 'shop_id', 'expires_at')
                ->firstOrFail();

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

    public function sinkronisasi() {
        $this->loadShopInfo();
        $userId = auth()->id();
        
        // Ambil total item
        $initialParams = [
            'offset' => 0,
            'page_size' => 10,
            'item_status' => ['NORMAL']
        ];
        
        $totalItems = $this->callShopeeAPI('product', 'get_item_list', $initialParams)['total_count'];
        
        // Proses dalam chunk 10 item
        $chunkSize = 10;
        $totalChunks = ceil($totalItems / $chunkSize);

        for ($offset = 0; $offset < $totalItems; $offset += $chunkSize) {
            $this->processChunk($userId, $offset, $chunkSize);
            unset($itemList, $itemBase);
            gc_collect_cycles();
        }

        Notification::make()->title('Sinkronisasi selesai!')->success()->send();
    }

    private function processChunk($userId, $offset, $pageSize) {
        try {
            $itemList = $this->get_item_list($offset, $pageSize);
            $itemIds = collect($itemList['item'] ?? [])->pluck('item_id')->toArray();
            
            if(empty($itemIds)) return;

            $itemBase = $this->get_item_base_info($itemIds);
            $itemBaseMap = collect($itemBase['item_list'] ?? [])->keyBy('item_id');

            foreach ($itemList['item'] ?? [] as $item) {
                $base = $itemBaseMap[$item['item_id']] ?? null;
                if (!$base) continue;

                $sku = Sku::updateOrCreate(
                    ['user_id' => $userId, 'item_id' => $item['item_id']],
                    $this->mapSkuData($base, $item)
                );

                if ($base['has_model'] ?? false) {
                    $this->processModels($sku, $item['item_id']);
                }
                
                unset($sku);
            }

        } catch (\Exception $e) {
            Log::error("Gagal proses chunk [$offset-$pageSize]: ".$e->getMessage());
        }
    }

    private function callShopeeAPI($service, $method, $params) {
        return retry(3, function() use ($service, $method, $params) {
            $response = Shoapi::call($service)
                ->access($method, $this->accessToken)
                ->shop($this->shopId)
                ->request($params)
                ->response();

            return json_decode(json_encode($response), true);
        }, 100);
    }

    private function get_item_list($offset = 0, $pageSize = 10) {
        return $this->callShopeeAPI('product', 'get_item_list', [
            'offset' => $offset,
            'page_size' => $pageSize,
            'item_status' => ['NORMAL'],
            'response_fields' => 'item_id,item_status'
        ]);
    }

    private function get_item_base_info(array $itemIds) {
        return $this->callShopeeAPI('product', 'get_item_base_info', [
            'item_id_list' => $itemIds,
            'response_fields' => 'category_id,item_name,item_sku,price_info,image,condition,brand,has_model'
        ]);
    }

    private function mapSkuData($base, $item) {
        return [
            'item_status' => $item['item_status'],
            'category_id' => $base['category_id'] ?? null,
            'item_name' => $base['item_name'] ?? '',
            'item_sku' => $base['item_sku'] ?? '',
            'currency' => $base['price_info'][0]['currency'] ?? 'IDR',
            'original_price' => $base['price_info'][0]['original_price'] ?? 0,
            'current_price' => $base['price_info'][0]['current_price'] ?? 0,
            'image_url' => $base['image']['image_url_list'][0] ?? null,
            'condition' => $base['condition'] ?? null,
            'original_brand_name' => $base['brand']['original_brand_name'] ?? null,
            'has_model' => $base['has_model'] ?? false,
        ];
    }

    private function processModels($sku, $itemId) {
        $modelList = $this->callShopeeAPI('product', 'get_model_list', [
            'item_id' => $itemId,
            'response_fields' => 'model_sku,price_info,tier_variation'
        ]);

        foreach ($modelList['model'] ?? [] as $model) {
            $variation = $modelList['tier_variation'][0] ?? [];
            $option = $variation['option_list'][$model['tier_index'][0]] ?? [];
            
            SkuModel::updateOrCreate(
                ['sku_id' => $sku->id, 'item_id' => $itemId, 'model_sku' => $model['model_sku']],
                [
                    'variation_name' => $variation['name'] ?? '',
                    'variation_option_name' => $option['option'] ?? '',
                    'image_url' => $option['image']['image_url'] ?? null,
                    'currency' => $sku->currency,
                    'original_price' => $model['price_info'][0]['original_price'] ?? 0,
                    'current_price' => $model['price_info'][0]['current_price'] ?? 0,
                ]
            );
        }
    }

    public function updateHargaModal($skuId) {
        $value = $this->harga_modal[$skuId] ?? null;
        if ($value !== null && $value !== '') {
            Sku::where('id', $skuId)->update(['harga_modal' => $value]);
            Notification::make()->title('Harga modal berhasil disimpan!')->success()->send();
        } else {
            Notification::make()->title('Harga modal tidak boleh kosong!')->danger()->send();
        }
    }

    public function updateHargaModalModel($modelId) {
        $value = $this->harga_modal_model[$modelId] ?? null;
        if ($value !== null && $value !== '') {
            SkuModel::where('id', $modelId)->update(['harga_modal' => $value]);
            Notification::make()->title('Harga modal varian berhasil disimpan!')->success()->send();
        } else {
            Notification::make()->title('Harga modal varian tidak boleh kosong!')->danger()->send();
        }
    }
}
?>

<x-layouts.app>
    @volt('produk-shopee')
        <x-app.container>
            <div class="flex items-center justify-between mb-4">
                <x-app.heading
                    title="Sinkronisasi Produk Shopee"
                    description="Sinkronisasi produk dari Shopee ke sistem, tambahkan informasi harga modal produk untuk setiap SKU dan varian. Data harga modal digunakan untuk menghitung profit."
                    :border="true"
                />
                <div class="flex justify-end gap-2">
                    <x-button wire:click="sinkronisasi" wire:loading.attr="disabled">
                        Sinkronisasi
                    </x-button>
                </div>
            </div>
            
            <div wire:loading>
                <div class="w-full h-2 bg-gray-200 rounded">
                    <div class="h-2 bg-blue-500 rounded animate-pulse" style="width: 80%"></div>
                </div>
            </div>

            <div class="grid gap-3">
                @foreach($this->skus as $sku)
                    <div class="bg-white rounded-lg shadow-md p-3 border border-gray-100/70 hover:border-gray-200 transition-all">
                        <div class="flex items-start gap-3">
                            <img 
                                src="{{ $sku->image_url }}" 
                                class="w-24 h-24 object-cover rounded-md border-2 border-white shadow-sm"
                                loading="lazy"
                                width="96"
                                height="96"
                                alt="{{ $sku->item_name }}">
                            
                            <div class="flex-1 min-w-0">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800 truncate">{{ $sku->item_name }}</h3>
                                    <span class="text-xs text-gray-500 block mt-0.5">SKU Induk: {{ $sku->item_sku }}</span>
                                </div>
                                
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">{{ $sku->original_brand_name }}</span>
                                    <span class="text-xs text-gray-500">{{ $sku->has_model ? 'Multi Varian' : 'Single Varian' }}</span>
                                </div>

                                <div class="flex items-center justify-between mt-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-xs text-gray-500">Harga Jual</span>
                                        <span class="text-sm font-medium text-blue-600">
                                            Rp{{ number_format($sku->current_price,0,',','.') }}
                                        </span>
                                    </div>
                                    
                                    @if (!$sku->has_model)
                                        <div class="flex flex-col">
                                            <span class="text-xs text-gray-500 mb-0.5">Harga Modal</span>
                                            <div class="flex items-center gap-1">
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        wire:model.defer="harga_modal.{{ $sku->id }}"
                                                        class="h-8 w-32 rounded-md border border-gray-300 bg-gray-50 pl-7 pr-2 text-xs focus:border-gray-400 focus:ring-1 focus:ring-gray-200 transition"
                                                        placeholder="Modal"
                                                    />
                                                    <span class="absolute left-2 top-2 text-xs text-gray-400">Rp</span>
                                                </div>
                                                <button
                                                    wire:click="updateHargaModal({{ $sku->id }})"
                                                    class="h-8 px-3 rounded-md bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold shadow transition"
                                                    type="button"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($sku->models->count())
                            <div class="mt-2 border-t pt-2">
                                <div class="text-xs font-medium text-gray-600">
                                    {{ $sku->models->count() }} Varian
                                </div>
                                
                                <div class="grid gap-1.5 mt-1.5" x-data="{ showAll: false }">
                                    @foreach($sku->models as $i => $model)
                                        <div
                                            x-show="showAll || {{ $i }} < 3"
                                            class="flex items-center gap-3 px-2 py-1.5 bg-gray-50/50 rounded-md hover:bg-gray-100/30"
                                            @if($i >= 3) x-cloak @endif
                                        >
                                            <img 
                                                src="{{ $model->image_url }}" 
                                                class="w-12 h-12 object-cover rounded border"
                                                loading="lazy"
                                                width="40"
                                                height="40"
                                                alt="Varian">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-700 truncate">{{ $model->variation_option_name }}</div>
                                                <div class="text-xs text-gray-400 truncate">Kode Variasi: {{ $model->model_sku }}</div>
                                            </div>
                                            <div class="flex flex-col items-end min-w-[90px]">
                                                <span class="text-xs text-gray-500">Harga Jual</span>
                                                <span class="text-xs text-blue-600 font-medium">
                                                    Rp{{ number_format($model->current_price,0,',','.') }}
                                                </span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs text-gray-500 mb-0.5">Harga Modal</span>
                                                <div class="flex items-center gap-1">
                                                    <div class="relative">
                                                        <input
                                                            type="number"
                                                            wire:model.defer="harga_modal_model.{{ $model->id }}"
                                                            class="h-7 w-32 rounded-md border border-gray-300 bg-gray-50 pl-6 pr-2 text-xs focus:border-gray-400 focus:ring-1 focus:ring-gray-200 transition"
                                                            placeholder="Modal"
                                                        />
                                                        <span class="absolute left-2 top-1.5 text-xs text-gray-400">Rp</span>
                                                    </div>
                                                    <button
                                                        wire:click="updateHargaModalModel({{ $model->id }})"
                                                        class="h-7 px-2 rounded-md bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold shadow transition"
                                                        type="button"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                
                                    @if($sku->models->count() > 3)
                                        <button
                                            class="text-xs text-blue-600 hover:underline mt-1"
                                            x-show="!showAll"
                                            @click="showAll = true"
                                            type="button"
                                        >
                                            Lihat semua ({{ $sku->models->count() - 2 }} SKU Produk)
                                        </button>
                                        <button
                                            class="text-xs text-gray-500 hover:underline mt-1"
                                            x-show="showAll"
                                            @click="showAll = false"
                                            type="button"
                                        >
                                            Tutup
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $this->skus->onEachSide(1)->links() }}
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>