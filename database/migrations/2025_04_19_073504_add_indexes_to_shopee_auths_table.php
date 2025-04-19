<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shopee_auths', function (Blueprint $table) {
            // Tambah index individual untuk optimasi query
            $table->index('shop_id');        // Untuk pencarian berdasarkan shop_id
            $table->index('user_id');         // Untuk relasi dengan user
            $table->index('expires_at');      // Untuk query pengecekan kedaluwarsa token
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopee_auths', function (Blueprint $table) {
            $table->dropIndex(['shop_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['expires_at']);
        });
    }
};
