<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KalkulatorShopee extends Model
{
    protected $fillable = [
        'hitung',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
