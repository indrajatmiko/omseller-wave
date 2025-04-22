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

    public $syncOffset = 0;
    public $syncTotalItems = 0;
    public $isSyncing = false;

    public $search = '';

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
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('item_name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('models', function($modelQuery) {
                        $modelQuery->where('variation_option_name', 'like', '%'.$this->search.'%');
                    });
                });
            })
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
    
    $initialParams = [
        'offset' => 0,
        'page_size' => 10,
        'item_status' => ['NORMAL']
    ];
    
    $response = $this->callShopeeAPI('product', 'get_item_list', $initialParams);
    $this->syncTotalItems = $response['total_count'] ?? 0;
    
    if($this->syncTotalItems > 0) {
        $this->syncOffset = 0;
        $this->isSyncing = true;
        $this->processNextChunk();
    } else {
        Notification::make()->title('Tidak ada produk untuk disinkronkan')->warning()->send();
    }
}

public function processNextChunk() {
    if(!$this->isSyncing || $this->syncOffset >= $this->syncTotalItems) {
        $this->isSyncing = false;
        Notification::make()->title('Sinkronisasi selesai!')->success()->send();
        return;
    }

    try {
        $this->processChunk(auth()->id(), $this->syncOffset, 10);
        $this->syncOffset += 10;
        $this->dispatch('process-next-chunk');
    } catch (\Exception $e) {
        $this->isSyncing = false;
        Notification::make()->title('Gagal sinkronisasi')->body($e->getMessage())->danger()->send();
    }
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
                    <x-button wire:click="sinkronisasi" wire:loading.attr="disabled" wire:target="sinkronisasi">
                        <span wire:loading.remove wire:target="sinkronisasi">Sinkronisasi</span>
                        <span wire:loading wire:target="sinkronisasi">Memproses...</span>
                    </x-button>
                </div>
            </div>
            
            <div class="mb-4 space-y-2">
                @if($isSyncing)
                    <div class="text-sm text-gray-600">
                        Memproses {{ min($syncOffset + 10, $syncTotalItems) }} dari {{ $syncTotalItems }} item...
                        @if(min($syncOffset + 10, $syncTotalItems) == $syncTotalItems)
                            <span class="ml-2 text-blue-600">(Tahap akhir, sebentar lagi)</span>
                        @endif
                    </div>
                    <div class="w-full bg-gray-200 rounded h-2">
                        <div class="bg-black h-2 rounded transition-all duration-300" 
                             style="width: {{ min(($syncOffset / $syncTotalItems) * 100, 100) }}%">
                        </div>
                    </div>
                    <div class="text-xs text-red-500">
                        Harap tunggu sampai proses selesai. Jangan tutup browser ini.
                    </div>
                @endif
            </div>
            
            <script>
                document.addEventListener('livewire:initialized', () => {
                    Livewire.on('process-next-chunk', () => {
                        setTimeout(() => {
                            @this.processNextChunk();
                        }, 100);
                    });
                });
            </script>

            <div class="mb-4">
                <div class="relative max-w-md">
                    <input 
                        type="text" 
                        wire:model.live="search"
                        placeholder="Cari produk berdasarkan nama..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm transition"
                    >
                    <div class="absolute left-3 top-2.5 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    @if($search)
                        <button 
                            wire:click="$set('search', '')" 
                            class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                        >
                            &times;
                        </button>
                    @endif
                </div>
            </div>
            <div class="grid gap-3">
                @if($this->skus->isEmpty())
                    <div class="bg-white rounded-lg p-6 text-center border border-dashed border-gray-200">
                        <div class="text-gray-400 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 text-sm">Tidak ditemukan produk dengan nama "{{ $search }}"</p>
                    </div>
                @else

                <div class="overflow-x-auto rounded-md shadow border bg-white">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Produk</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Brand</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Harga Jual</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Harga Modal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($this->skus as $sku)
                                @if($sku->has_model && $sku->models->count())
                                    @foreach($sku->models as $model)
                                        <tr class="hover:bg-gray-100 transition">
                                            <td class="px-6 py-3 flex items-center gap-3">
                                                <img src="{{ $model->image_url ?? $sku->image_url }}" alt="Varian" class="w-16 h-16 object-cover rounded-lg border border-blue-100 shadow-sm" loading="lazy">
                                                <div>
                                                    <div class="font-semibold text-gray-900 truncate">
                                                        {{ \Illuminate\Support\Str::limit($sku->item_name, 50, '..') }}
                                                    </div>
                                                    <div class="text-xs text-gray-600">SKU Variasi: {{ $model->model_sku }}</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3 text-gray-700 align-middle">{{ $sku->original_brand_name ?? '-' }}</td>
                                            <td class="px-6 py-3 text-blue-700 font-bold text-end">
                                                {{ number_format($model->current_price,0,',','.') }}
                                            </td>
                                            <td class="px-6 py-3 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <div class="relative">
                                                        <input
                                                            type="number"
                                                            wire:model.defer="harga_modal_model.{{ $model->id }}"
                                                            class="h-9 w-32 rounded-lg border border-gray-200 bg-gray-50 pl-8 pr-2 text-sm focus:border-gray-400 focus:ring-2 focus:ring-gray-100 transition text-end"
                                                            placeholder="Modal"
                                                        />
                                                    </div>
                                                    <button
                                                        wire:click="updateHargaModalModel({{ $model->id }})"
                                                        class="h-9 px-3 rounded-lg 
                                                            {{ !empty($harga_modal_model[$model->id]) ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700' }} 
                                                            text-white text-xs font-semibold shadow transition"
                                                        type="button"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr class="hover:bg-gray-100 transition">
                                        <td class="px-6 py-3 flex items-center gap-3">
                                            <img src="{{ $sku->image_url }}" alt="Produk" class="w-16 h-16 object-cover rounded-lg border border-blue-100 shadow-sm" loading="lazy">
                                            <div>
                                                <div class="font-semibold text-gray-900 truncate">
                                                    {{ \Illuminate\Support\Str::limit($sku->item_name, 50, '..') }}
                                                </div>
                                                <div class="text-xs text-gray-600">SKU Induk: {{ $sku->item_sku }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 align-middle">{{ $sku->original_brand_name ?? '-' }}</td>
                                        <td class="px-6 py-3 text-blue-700 font-bold text-end">
                                            {{ number_format($sku->current_price,0,',','.') }}
                                        </td>
                                        <td class="px-6 py-3 align-middle">
                                            <div class="flex items-center gap-2">
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        wire:model.defer="harga_modal.{{ $sku->id }}"
                                                        class="h-9 w-32 rounded-lg border border-gray-200 bg-gray-50 pl-8 pr-2 text-sm focus:border-gray-400 focus:ring-2 focus:ring-gray-100 transition text-end"
                                                        placeholder="Modal"
                                                    />
                                                </div>
                                                <button
                                                    wire:click="updateHargaModal({{ $sku->id }})"
                                                    class="h-9 px-3 rounded-lg 
                                                        {{ !empty($harga_modal[$sku->id]) ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700' }} 
                                                        text-white text-xs font-semibold shadow transition"
                                                    type="button"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-8 text-blue-300">Tidak ada produk ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @endif
            </div>

            <div class="mt-4">
                {{ $this->skus->onEachSide(1)->links() }}
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>