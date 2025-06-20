<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductScrapeController extends Controller
{
    public function getStats()
    {
        $user = Auth::user();
        $count = Product::where('user_id', $user->id)->count();
        return response()->json(['dbCount' => $count]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scrapedData' => 'required|array|min:1',
            'scrapedData.*.shopee_product_id' => 'required|numeric',
            'scrapedData.*.product_name' => 'required|string',
            // ... tambahkan validasi lain jika perlu
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $productsCreated = 0;
        $productsUpdated = 0;

        foreach ($request->scrapedData as $productData) {
            try {
                DB::transaction(function () use ($user, $productData, &$productsCreated, &$productsUpdated) {
                    $product = Product::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'shopee_product_id' => $productData['shopee_product_id'],
                        ],
                        [
                            'product_name' => $productData['product_name'],
                            'parent_sku' => $productData['parent_sku'],
                            'image_url' => $productData['image_url'],
                            // (PERBAIKAN) Beri nilai default jika null
                            'total_sales' => $productData['total_sales'] ?? 0,
                            'total_stock' => $productData['total_stock'] ?? 0,
                        ]
                    );

                    if ($product->wasRecentlyCreated) {
                        $productsCreated++;
                    } else {
                        $productsUpdated++;
                    }

                    // Hapus varian lama dan buat ulang untuk memastikan sinkronisasi
                    $product->variants()->delete();

                    if (!empty($productData['variants'])) {
                        $product->variants()->createMany($productData['variants']);
                    }
                });
            } catch (Throwable $e) {
                Log::error('Failed to store product scrape data for user ' . $user->id, [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json(['message' => 'Kesalahan internal server: ' . $e->getMessage()], 500);
            }
        }

        return response()->json([
            'message' => 'Data produk berhasil diproses.',
            'created' => $productsCreated,
            'updated' => $productsUpdated,
        ], 200);
    }
}