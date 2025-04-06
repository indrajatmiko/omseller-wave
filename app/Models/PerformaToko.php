<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformaToko extends Model
{
    use HasFactory;

    protected $table = 'performa_toko';
    protected $guarded = [];
    protected $fillable = [
        'kode_produk',
        'produk',
        'status_produk_saat_ini',
        'kode_variasi',
        'nama_variasi',
        'status_variasi_saat_ini',
        'sku_induk',
        'pengunjung_produk_kunjungan',
        'halaman_produk_dilihat',
        'pengunjung_melihat_tanpa_membeli',
        'tingkat_pengunjung_melihat_tanpa_membeli',
        'klik_pencarian',
        'suka',
        'pengunjung_produk_menambahkan_ke_keranjang',
        'dimasukkan_ke_keranjang_produk',
        'tingkat_konversi_ke_keranjang',
        'total_pembeli_pesanan_dibuat',
        'produk_pesanan_dibuat',
        'total_penjualan_pesanan_dibuat',
        'tingkat_konversi_pesanan_dibuat',
        'total_pembeli_pesanan_siap_dikirim',
        'produk_pesanan_siap_dikirim',
        'penjualan_pesanan_siap_dikirim',
        'tingkat_konversi_pesanan_siap_dikirim',
        'tingkat_konversi_pesanan_siap_vs_dibuat',
        'pembelian_ulang_pesanan_siap_dikirim',
        'rata_hari_pembelian_ulang',
        'user_id',
        'username',
        'bulan',
        'tahun',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}