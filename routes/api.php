<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return auth()->user();
// });

Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        // 'auth:api' akan memastikan $request->user() terisi
        return $request->user(); 
    });
    Route::post('/scrape-data', [App\Http\Controllers\Api\ScrapeDataController::class, 'store']);
    Route::get('/scraped-dates/{campaign_id}', [ScrapeDataController::class, 'getScrapedDates']);

});

Wave::api();

// Posts Example API Route
Route::group(['middleware' => 'auth:api'], function () {
    Route::get('/posts', '\App\Http\Controllers\Api\ApiController@posts');
});