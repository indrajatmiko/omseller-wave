<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\ShopeeAuth;

class FetchShopeeOrdersLastYearJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        $shop = ShopeeAuth::where('user_id', $this->user->id)->firstOrFail();

        $startDate = Carbon::now()->subYear()->startOfYear();
        $endDate = Carbon::now()->endOfMonth();

        // Pastikan startDate tidak melebihi endDate
        if ($startDate > $endDate) {
            $startDate = $endDate->copy()->subDay();
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalChunks = ceil($totalDays / 15);

        // Inisialisasi cache dengan total yang benar
        Cache::put("shopee_sync_last_year_{$this->user->id}", [
            'total' => $totalChunks,
            'processed' => 0,
            'current_period' => '',
            'progress' => 0,
            'order_count' => 0
        ], now()->addDay());

        // Dispatch chunk dengan batasan akhir
        $currentDate = $startDate->copy();
        $chunkCount = 0; // Untuk debug

        while ($currentDate <= $endDate) {
            $chunkEndDate = $currentDate->copy()->addDays(14); // 14 hari untuk mencakup 15 hari (inklusif)
            
            // Batasi chunkEndDate ke endDate
            if ($chunkEndDate > $endDate) {
                $chunkEndDate = $endDate->copy();
            }
        
            // Dispatch job
            ProcessShopeeOrderLastYearChunk::dispatch(
                $this->user,
                $currentDate->timestamp,
                $chunkEndDate->timestamp
            );
        
            // Log chunk yang di-dispatch
            // \Log::info("Chunk {$chunkCount}: {$currentDate->toDateString()} - {$chunkEndDate->toDateString()}");
        
            // Update currentDate ke hari setelah chunkEndDate
            $currentDate = $chunkEndDate->addDay();
            $chunkCount++;
        }

        // Log jumlah chunk yang di-dispatch (opsional)
        // \Log::info("Total chunks dispatched: {$chunkCount}, calculated: {$totalChunks}");
    }
}