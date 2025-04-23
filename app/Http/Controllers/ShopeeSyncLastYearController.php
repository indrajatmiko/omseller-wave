<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\FetchShopeeOrdersLastYearJob;
use Illuminate\Support\Facades\Cache;

class ShopeeSyncLastYearController extends Controller
{
    public function sync()
    {
        FetchShopeeOrdersLastYearJob::dispatch(auth()->user());
        return back()->with('status', 'Sinkronisasi dimulai!');
    }

    public function progress()
    {
        $progress = Cache::get("shopee_sync_last_year_".auth()->id(), [
            'progress' => 0,
            'current_period' => '',
            'processed' => 0,
            'total' => 1
        ]);
        
        return response()->json($progress);
    }
}
