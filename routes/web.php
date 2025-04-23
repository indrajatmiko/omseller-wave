<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use Wave\Facades\Wave;
use App\Http\Controllers\ShopeeSyncController;

// Wave routes
Wave::routes();

Route::post('/sinkronisasi/sync-pesanan-shopee', function() {
    \App\Jobs\FetchShopeeOrdersJob::dispatch(auth()->user());
    return back()->with('status', 'Proses sinkronisasi dimulai!');
})->middleware('auth')->name('shopee.sync');

Route::get('/sync-progress', function() {
    $progress = Cache::get("shopee_sync_".auth()->id(), [
        'progress' => 0,
        'current_period' => '',
        'order_count' => 0,
        'total' => 1
    ]);
    return response()->json($progress);
})->middleware('auth');

Route::post('/clear-sync-cache', function() {
    Cache::forget("shopee_sync_".auth()->id());
    return response()->json(['status' => 'cleared']);
})->middleware('auth');