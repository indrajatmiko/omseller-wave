<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\{ShopeeAuth, Sku, SkuModel};
use Muhanz\Shoapi\Facades\Shoapi;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

class ProcessShopeeChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $offset,
        public int $pageSize
    ) {}

    public function handle()
    {
        $shop = ShopeeAuth::where('user_id', $this->userId)->firstOrFail();

        // Refresh token jika perlu
        if ($shop->needsTokenRefresh()) {
            app(ShopeeTokenService::class)->refreshAccessToken($shop);
            $shop->refresh();
        }

        $accessToken = $shop->access_token;
        $shopId = $shop->shop_id;

        // Ambil item list untuk chunk ini
        $itemList = $this->callShopeeAPI(
            $accessToken,
            $shopId,
            'product',
            'get_item_list',
            [
                'offset' => $this->offset,
                'page_size' => $this->pageSize,
                'item_status' => ['NORMAL'],
                'response_fields' => 'item_id,item_status'
            ]
        );

        $itemIds = collect($itemList['item'] ?? [])->pluck('item_id')->toArray();
        
        
        if (empty($itemIds)) return;
        
        // Split item_ids ke dalam chunk 50
        $itemIdChunks = array_chunk($itemIds, 50);
        
        $itemBaseMap = collect();
        
        foreach ($itemIdChunks as $chunk) {
            $itemBase = $this->callShopeeAPI(
                $accessToken,
                $shopId,
                'product',
                'get_item_base_info',
                [
                    'item_id_list' => $chunk,
                    'response_fields' => 'item_id,category_id,item_name,item_sku,price_info,image,condition,brand,has_model'
                ]
            );

            $itemBaseMap = $itemBaseMap->merge(
                collect($itemBase['item_list'] ?? [])->keyBy('item_id')
            );
        }
        
        foreach ($itemList['item'] ?? [] as $item) {
            $base = $itemBaseMap[$item['item_id']] ?? null;
            if (!$base) continue;
    
            $sku = Sku::updateOrCreate(
                [
                    'user_id' => $this->userId,
                    'item_id' => $item['item_id'],
                ],
                $this->mapSkuData($base, $item)
            );
    
            if ($base['has_model'] ?? false) {
                $this->processModels($sku, $item['item_id'], $accessToken, $shopId);
            }
        }

        Log::info('Item IDs dari get_item_list:', ['item_ids' => $itemIds]);
        Log::info('Item IDs dari get_item_base_info:', ['item_ids' => $itemBaseMap->keys()]);
    }

    private function callShopeeAPI($accessToken, $shopId, $service, $method, $params)
    {
        return retry(3, function() use ($service, $method, $params, $accessToken, $shopId) {
            $response = Shoapi::call($service)
                ->access($method, $accessToken)
                ->shop($shopId)
                ->request($params)
                ->response();

            return json_decode(json_encode($response), true);
        }, 100);
    }

    private function mapSkuData($base, $item)
    {
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

    private function processModels($sku, $itemId, $accessToken, $shopId)
    {
        $modelList = $this->callShopeeAPI(
            $accessToken,
            $shopId,
            'product',
            'get_model_list',
            [
                'item_id' => $itemId,
                'response_fields' => 'model_sku,price_info,tier_variation'
            ]
        );

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
}