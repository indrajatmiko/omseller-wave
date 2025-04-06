<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('performa_toko', function (Blueprint $table) {
            // Kolom Utama
            $table->id();
            $table->string('kode_produk');
            $table->text('produk');
            $table->string('status_produk_saat_ini');
            $table->string('kode_variasi')->nullable();
            $table->string('nama_variasi')->nullable();
            $table->string('status_variasi_saat_ini')->nullable();
            $table->string('sku_induk');
            
            // Metrik Performa
            $table->integer('pengunjung_produk_kunjungan')->default(0);
            $table->integer('halaman_produk_dilihat')->default(0);
            $table->integer('pengunjung_melihat_tanpa_membeli')->default(0);
            $table->string('tingkat_pengunjung_melihat_tanpa_membeli')->default('0%');
            $table->integer('klik_pencarian')->default(0);
            $table->integer('suka')->default(0);
            $table->integer('pengunjung_produk_menambahkan_ke_keranjang')->default(0);
            $table->integer('dimasukkan_ke_keranjang_produk')->default(0);
            $table->string('tingkat_konversi_ke_keranjang')->default('0%');
            $table->integer('total_pembeli_pesanan_dibuat')->default(0);
            $table->integer('produk_pesanan_dibuat')->default(0);
            $table->decimal('total_penjualan_pesanan_dibuat', 15, 2)->default(0);
            $table->string('tingkat_konversi_pesanan_dibuat')->default('0%');
            $table->integer('total_pembeli_pesanan_siap_dikirim')->default(0);
            $table->integer('produk_pesanan_siap_dikirim')->default(0);
            $table->decimal('penjualan_pesanan_siap_dikirim', 15, 2)->default(0);
            $table->string('tingkat_konversi_pesanan_siap_dikirim')->default('0%');
            $table->string('tingkat_konversi_pesanan_siap_vs_dibuat')->default('0%');
            $table->string('pembelian_ulang_pesanan_siap_dikirim')->default('0%');
            $table->decimal('rata_hari_pembelian_ulang', 5, 1)->default(0);
            
            // Tenancy
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('username');
            $table->integer('bulan');
            $table->integer('tahun');
            $table->timestamps();

            // Tambahkan indeks
            $table->index(['user_id', 'username', 'bulan', 'tahun']);
            $table->index('kode_produk');
            $table->index('sku_induk');
        });
    }

    public function down()
    {
        Schema::dropIfExists('performa_toko');
    }
};