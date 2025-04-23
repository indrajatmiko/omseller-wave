<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Muhanz\Shoapi\Facades\Shoapi;
use App\Models\ShopeeAuth;
use App\Models\User;
use Carbon\Carbon;
use App\Services\ShopeeTokenService;

class ProcessShopeeOrderLastYearChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    protected $user;
    protected $timeFrom;
    protected $timeTo;

    public function __construct(User $user, int $timeFrom, int $timeTo)
    {
        $this->user = $user;
        $this->timeFrom = $timeFrom;
        $this->timeTo = $timeTo;
    }

    public function handle()
    {
        $cacheKey = "shopee_sync_last_year_{$this->user->id}";
        $lockKey = $cacheKey . '_lock';
        $lock = Cache::lock($lockKey, 10);
        // \Log::info("Processing chunk: {$this->timeFrom} to {$this->timeTo}");

        try {
            $lock->block(5);

            $progress = Cache::get($cacheKey, [
                'total' => 0,
                'processed' => 0,
                'current_period' => '',
                'progress' => 0,
                'order_count' => 0
            ]);

            $shop = ShopeeAuth::where('user_id', $this->user->id)->firstOrFail();
            
            // Refresh token jika perlu
            if ($shop->needsTokenRefresh()) {
                app(ShopeeTokenService::class)->refreshAccessToken($shop);
                $shop->refresh();
            }

            // Request ke API
            $params = [
                'time_range_field' => 'create_time',
                'time_from' => $this->timeFrom,
                'time_to' => $this->timeTo,
                'page_size' => 50,
                // 'order_status' => 'COMPLETED'
            ];

            $response = retry(3, function() use ($shop, $params) {
                $result = Shoapi::call('order')
                    ->access('get_order_list', $shop->access_token)
                    ->shop($shop->shop_id)
                    ->request($params)
                    ->response();
                return json_decode(json_encode($result), true);
            }, 2000);

            // Proses response
            $orderSns = [];
            if (isset($response['order_list'])) { // Jika respons berupa array
                foreach ($response['order_list'] as $order) {
                    $orderSns[] = $order['order_sn']; // Gunakan array syntax
                }
            }

            // Update cache
            $progress['processed']++;
            $progress['progress'] = ($progress['processed'] / $progress['total']) * 100;
            $progress['current_period'] = Carbon::createFromTimestamp($this->timeFrom)
                ->format('F Y');    
            $progress['order_count'] += count($orderSns ?? []);

            Cache::put($cacheKey, $progress, now()->addDay());

            // Simpan ke file
            if (!empty($orderSns)) {
                $chunk = implode(',', array_slice($orderSns, 0, 50));
                Storage::append(
                    "shopee_orders/{$this->user->id}.json",
                    $chunk
                );
            }

        } catch (\Exception $e) {
            // Handle error
            Cache::put($cacheKey, [...$progress, 'error' => $e->getMessage()], now()->addDay());
            throw $e;
        } finally {
            optional($lock)->release();
        }
        
    }
}