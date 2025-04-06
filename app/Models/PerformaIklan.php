<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformaIklan extends Model
{
    use HasFactory;

    protected $table = 'performa_iklan';
    protected $guarded = [];
    protected $fillable = [
        'nama_iklan',
        'status',
        'jenis_iklan',
        'kode_produk',
        'tampilan_iklan',
        'mode_bidding',
        'penempatan_iklan',
        'tanggal_mulai',
        'tanggal_selesai',
        'dilihat',
        'jumlah_klik',
        'persentase_klik',
        'konversi',
        'konversi_langsung',
        'tingkat_konversi',
        'tingkat_konversi_langsung',
        'biaya_per_konversi',
        'biaya_per_konversi_langsung',
        'produk_terjual',
        'terjual_langsung',
        'omzet_penjualan',
        'penjualan_langsung_gmv',
        'biaya',
        'efektifitas_iklan',
        'efektivitas_langsung',
        'acos',
        'acos_langsung',
        'jumlah_produk_dilihat',
        'jumlah_klik_produk',
        'persentase_klik_produk',
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