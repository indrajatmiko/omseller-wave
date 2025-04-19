<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeAuth extends Model
{
    protected $fillable = [
        'shop_id',
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'shop_info',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function needsTokenRefresh(): bool
    {
        // Refresh 10 menit sebelum access_token kadaluarsa
        return now()->addMinutes(10)->gt($this->expires_at);
    }
}
