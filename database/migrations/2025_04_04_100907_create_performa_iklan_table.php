<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('performa_iklan', function (Blueprint $table) {
            // Kolom Utama
            $table->id();
            $table->string('nama_iklan');
            $table->string('status');
            $table->string('jenis_iklan');
            $table->string('kode_produk');
            $table->string('tampilan_iklan')->nullable();
            $table->string('mode_bidding');
            $table->string('penempatan_iklan');
            $table->datetime('tanggal_mulai')->nullable();
            $table->datetime('tanggal_selesai')->nullable();
            
            // Metrik Performa
            $table->integer('dilihat')->default(0);
            $table->integer('jumlah_klik')->default(0);
            $table->string('persentase_klik')->default('0%');
            $table->integer('konversi')->default(0);
            $table->integer('konversi_langsung')->default(0);
            $table->string('tingkat_konversi')->default('0%');
            $table->string('tingkat_konversi_langsung')->default('0%');
            $table->decimal('biaya_per_konversi', 15, 2)->default(0);
            $table->decimal('biaya_per_konversi_langsung', 15, 2)->default(0);
            $table->integer('produk_terjual')->default(0);
            $table->integer('terjual_langsung')->default(0);
            $table->decimal('omzet_penjualan', 15, 2)->default(0);
            $table->decimal('penjualan_langsung_gmv', 15, 2)->default(0);
            $table->decimal('biaya', 15, 2)->default(0);
            $table->string('efektifitas_iklan')->default('0%');
            $table->string('efektivitas_langsung')->default('0%');
            $table->string('acos')->default('0%');
            $table->string('acos_langsung')->default('0%');
            $table->integer('jumlah_produk_dilihat')->default(0);
            $table->integer('jumlah_klik_produk')->default(0);
            $table->string('persentase_klik_produk')->default('0%');
            
            // Tenancy
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('username');
            $table->integer('bulan');
            $table->integer('tahun');
            $table->timestamps();

            // Tambahkan indeks
            $table->index(['user_id', 'username', 'bulan', 'tahun']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('performa_iklan');
    }
};