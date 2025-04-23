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
use App\Http\Controllers\ShopeeSyncLastYearController;

// Wave routes
Wave::routes();

Route::post('/sinkronisasi/sync-last-year-pesanan-shopee', function() {
    \App\Jobs\FetchShopeeOrdersLastYearJob::dispatch(auth()->user());
    return back()->with('status', 'Proses sinkronisasi dimulai!');
})->middleware('auth')->name('shopee.sync');

Route::get('/sync-last-year-progress', function() {
    $progress = Cache::get("shopee_sync_last_year_".auth()->id(), [
        'progress' => 0,
        'current_period' => '',
        'order_count' => 0,
        'total' => 1
    ]);
    return response()->json($progress);
})->middleware('auth');

Route::post('/clear-sync-last-year-cache', function() {
    Cache::forget("shopee_sync_last_year_".auth()->id());
    return response()->json(['status' => 'cleared']);
})->middleware('auth');