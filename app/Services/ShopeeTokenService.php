<?php
namespace App\Services;

use App\Models\ShopeeAuth;
use Muhanz\Shoapi\Facades\Shoapi;

class ShopeeTokenService
{
    public function refreshAccessToken(ShopeeAuth $shop): void
    {
        $resp = Shoapi::call('auth')
            ->access('refresh_access_token')
            ->shop($shop->shop_id)
            ->request([
                'refresh_token' => $shop->refresh_token,
                'shop_id' => $shop->shop_id,
            ])->response();
        $response = json_decode(json_encode($resp), true);

        if ($response['api_status'] === 'success') {
            $shop->update([
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'], // Perbarui refresh_token juga
                'expires_at' => now()->addSeconds($response['expire_in']),
            ]);
        } else {
            throw new \Exception("Gagal refresh token. Response: " . json_encode($response));
        }
    }
}