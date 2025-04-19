<?php

namespace App\Jobs;

use App\Models\ShopeeAuth;
use App\Services\ShopeeTokenService;
use Illuminate\Support\Facades\Log;

class RefreshShopeeTokens
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {    
        try {
            // Ambil toko yang access_token-nya kedaluwarsa dalam 10 menit
            $shops = ShopeeAuth::where('expires_at', '<', now()->addMinutes(10))->get();
            Log::info('Jumlah toko ditemukan: ' . $shops->count());
    
            foreach ($shops as $shop) {
                try {
                    // Log::info('Memperbarui token untuk toko: ' . $shop->shop_id);
                    app(ShopeeTokenService::class)->refreshAccessToken($shop);
                } catch (\Exception $e) {
                    Log::error('Gagal memperbarui token Shopee untuk toko ' . $shop->shop_id, [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error di RefreshShopeeTokens: ' . $e->getMessage());
        }
    }
}