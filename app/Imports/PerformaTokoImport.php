<?php

namespace App\Imports;
use App\Models\PerformaToko;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class PerformaTokoImport implements ToModel, WithStartRow {
    protected $username;
    protected $userId;
    protected $bulan;
    protected $tahun;

    public function __construct($username, $userId, $bulan, $tahun) {
        $this->username = $username;
        $this->userId = $userId;
        $this->bulan = $bulan;
        $this->tahun = $tahun;
    }

    public function startRow(): int
    {
        return 2; // Mulai dari baris 9 (B9)
    }

    public function model(array $row) {
        return new PerformaToko([
            'kode_produk' => $row[0],
            'produk' => $row[1],
            'status_produk_saat_ini' => $row[2],
            'kode_variasi' => $row[3],
            'nama_variasi' => $row[4],
            'status_variasi_saat_ini' => $row[5],
            'sku_induk' => $row[7], // Skip kolom 6 (kode variasi duplikat)
            'pengunjung_produk_kunjungan' => (int) $row[8],
            'halaman_produk_dilihat' => (int) $row[9],
            'pengunjung_melihat_tanpa_membeli' => (int) $row[10],
            'tingkat_pengunjung_melihat_tanpa_membeli' => $row[11],
            'klik_pencarian' => (int) $row[12],
            'suka' => (int) $row[13],
            'pengunjung_produk_menambahkan_ke_keranjang' => (int) $row[14],
            'dimasukkan_ke_keranjang_produk' => (int) $row[15],
            'tingkat_konversi_ke_keranjang' => $row[16],
            'total_pembeli_pesanan_dibuat' => (int) $row[17],
            'produk_pesanan_dibuat' => (int) $row[18],
            'total_penjualan_pesanan_dibuat' => (float) str_replace('.', '', $row[19]),
            'tingkat_konversi_pesanan_dibuat' => $row[20],
            'total_pembeli_pesanan_siap_dikirim' => (int) $row[21],
            'produk_pesanan_siap_dikirim' => (int) $row[22],
            'penjualan_pesanan_siap_dikirim' => (float) str_replace('.', '', $row[23]),
            'tingkat_konversi_pesanan_siap_dikirim' => $row[24],
            'tingkat_konversi_pesanan_siap_vs_dibuat' => $row[25],
            'pembelian_ulang_pesanan_siap_dikirim' => $row[26],
            'rata_hari_pembelian_ulang' => (float) str_replace(',', '.', $row[27]),
            'user_id' => $this->userId,
            'username' => $this->username,
            'bulan' => $this->bulan,
            'tahun' => $this->tahun
        ]);
    }
}