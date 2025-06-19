<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScrapeDataController extends Controller
{
    public function store(Request $request)
    {
        // Validasi data tingkat atas
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|integer',
            'aggregatedData' => 'required|array|min:1',
            'aggregatedData.*.scrapeDate' => 'required|date_format:Y-m-d',
            'aggregatedData.*.data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $user = Auth::user();
        $reportsCreated = 0;
        $reportsSkipped = 0;

        foreach ($validated['aggregatedData'] as $dailyData) {
            try {
                // Semua operasi untuk satu hari dibungkus dalam transaksi untuk menjaga integritas data anak
                DB::transaction(function () use ($user, $validated, $dailyData, &$reportsCreated, &$reportsSkipped) {
                    
                    $data = $dailyData['data'];
                    $productInfo = $data['productInfo'] ?? [];
                    $perfMetrics = $data['performanceMetrics'] ?? [];

                    // Metode firstOrCreate akan mencoba membuat record baru dengan data dari parameter kedua.
                    // Jika record dengan atribut dari parameter pertama sudah ada, ia akan mengambil record tersebut
                    // tanpa mencoba membuat yang baru, sehingga tidak ada error UNIQUE constraint.
                    $report = CampaignReport::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'campaign_id' => $validated['campaign_id'],
                            'scrape_date' => $dailyData['scrapeDate'],
                        ],
                        [
                            'date_range_text' => $productInfo['rentang_tanggal'] ?? null,
                            'nama_produk' => $productInfo['nama_produk'] ?? null,
                            'gambar_url' => $productInfo['gambar'] ?? null,
                            'status_iklan' => $productInfo['status_iklan'] ?? null,
                            'modal' => $productInfo['modal'] ?? null,
                            'periode_iklan' => $productInfo['periode_iklan'] ?? null,
                            'penempatan_iklan' => $productInfo['penempatan_iklan'] ?? null,
                            'mode_bidding' => $productInfo['mode_bidding'] ?? null,
                            'bidding_dinamis' => $productInfo['bidding_dinamis'] ?? null,
                            'target_roas' => $productInfo['target_roas'] ?? null,
                            'dilihat' => $perfMetrics['dilihat'] ?? null,
                            'klik' => $perfMetrics['klik'] ?? null,
                            'persentase_klik' => $perfMetrics['persentase_klik'] ?? null,
                            'biaya' => $perfMetrics['biaya'] ?? null,
                            'pesanan' => $perfMetrics['pesanan'] ?? null,
                            'produk_terjual' => $perfMetrics['produk_terjual_di_iklan'] ?? $perfMetrics['produk_terjual'] ?? null,
                            'omzet_iklan' => $perfMetrics['omzet_iklan'] ?? null,
                            'efektivitas_iklan' => $perfMetrics['efektivitas_iklan_(roas)'] ?? null,
                            'cir' => $perfMetrics['cir_(acos)'] ?? null,
                        ]
                    );

                    // Properti $report->wasRecentlyCreated akan bernilai true HANYA jika firstOrCreate
                    // berhasil membuat record baru. Jika record sudah ada, nilainya akan false.
                    if ($report->wasRecentlyCreated) {
                        $reportsCreated++;
                        
                        // Simpan data anak hanya jika laporan utama baru dibuat
                        if (!empty($data['keywordPerformance'])) {
                            foreach ($data['keywordPerformance'] as $kw) {
                                $report->keywordPerformances()->create($this->flattenMetrics($kw));
                            }
                        }

                        if (!empty($data['recommendationPerformance'])) {
                            foreach ($data['recommendationPerformance'] as $rec) {
                                $report->recommendationPerformances()->create($this->flattenMetrics($rec, true));
                            }
                        }
                    } else {
                        // Jika record sudah ada, kita lewati dan catat. Tidak ada error yang terjadi.
                        $reportsSkipped++;
                    }
                });
            } catch (\Throwable $e) {
                // Blok catch ini sekarang hanya akan menangkap error tak terduga lainnya.
                Log::error('Failed to store scrape data for user ' . $user->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['message' => 'Terjadi kesalahan internal saat menyimpan data: ' . $e->getMessage()], 500);
            }
        }

        return response()->json([
            'message' => 'Data berhasil diproses.',
            'created' => $reportsCreated,
            'skipped' => $reportsSkipped,
        ], 200);
    }

    private function flattenMetrics(array $item, string $type): array
    {
        $base = [];
        if ($type === 'keyword') {
            $base = [
                'kata_pencarian' => $item['kata_pencarian'] ?? null,
                'tipe_pencocokan' => $item['tipe_pencocokan'] ?? null,
                'per_klik' => $item['per_klik'] ?? null,
                'disarankan' => $item['disarankan'] ?? null,
            ];
        } elseif ($type === 'recommendation') {
            $base = [
                'penempatan' => $item['penempatan'] ?? null,
                'harga_bid' => $item['harga_bid'] ?? null,
                'disarankan' => $item['disarankan'] ?? null,
            ];
        }
        
        $metricsMap = [
            'iklan_dilihat' => 'iklan_dilihat',
            'jumlah_klik' => 'jumlah_klik',
            'persentase_klik' => 'persentase_klik',
            'biaya_iklan' => 'biaya_iklan',
            'penjualan_dari_iklan' => 'penjualan_dari_iklan',
            'konversi' => 'konversi',
            'produk_terjual' => 'produk_terjual',
            'roas' => 'roas',
            'acos' => 'acos',
            'tingkat_konversi' => 'tingkat_konversi',
            'biaya_per_konversi' => 'biaya_per_konversi',
            'peringkat_rata_rata' => 'peringkat_rata_rata'
        ];

        foreach($metricsMap as $key => $db_prefix) {
            $base[$db_prefix . '_value'] = $item[$key]['value'] ?? null;
            $base[$db_prefix . '_delta'] = $item[$key]['delta'] ?? null;
        }
        
        return $base;
    }
}