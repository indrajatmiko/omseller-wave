<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sku extends Model
{
    protected $guarded = [];
    
    public function models()
    {
        return $this->hasMany(SkuModel::class);
    }
}
