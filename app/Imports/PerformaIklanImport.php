<?php

namespace App\Imports;

use App\Models\PerformaIklan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Carbon\Carbon;

class PerformaIklanImport implements ToModel, WithStartRow
{
    protected $username;
    protected $userId;
    protected $bulan;
    protected $tahun;

    public function __construct($username, $userId, $bulan, $tahun)
    {
        $this->username = $username;
        $this->userId = $userId;
        $this->bulan = $bulan;
        $this->tahun = $tahun;
    }

    public function startRow(): int
    {
        return 9; // Mulai dari baris 9 (B9)
    }

    public function model(array $row)
    {
        $tanggalSelesai = isset($row[9]) && $row[9] !== 'Tidak Terbatas' 
        ? Carbon::createFromFormat('d/m/Y H:i:s', $row[9]) 
        : null;

        return new PerformaIklan([
            'nama_iklan' => $row[1] ?? 'N/A',
            'status' => $row[2] ?? 'N/A',
            'jenis_iklan' => $row[3] ?? 'N/A',
            'kode_produk' => $row[4] ?? 'N/A',
            'tampilan_iklan' => $row[5] ?? '-', // Kolom ke-6 (indeks 5)
            'mode_bidding' => $row[6] ?? 'Manual',
            'penempatan_iklan' => $row[7] ?? 'Semua Penempatan',
            'tanggal_mulai' => isset($row[8]) ? Carbon::createFromFormat('d/m/Y H:i:s', $row[8]) : now(),
            'tanggal_selesai' => $tanggalSelesai,
            'dilihat' => (int) ($row[10] ?? 0),
            'jumlah_klik' => (int) ($row[11] ?? 0),
            'persentase_klik' => $row[12] ?? 0,
            'konversi' => (int) ($row[13] ?? 0),
            'konversi_langsung' => (int) ($row[14] ?? 0),
            'tingkat_konversi' => ($row[15] ?? 0),
            'tingkat_konversi_langsung' => ($row[16] ?? 0),
            'biaya_per_konversi' => (float) ($row[17] ?? 0),
            'biaya_per_konversi_langsung' => (float) ($row[18] ?? 0),
            'produk_terjual' => (int) ($row[19] ?? 0),
            'terjual_langsung' => (int) ($row[20] ?? 0),
            'omzet_penjualan' => (float) str_replace('.', '', ($row[21] ?? 0)),
            'penjualan_langsung_gmv' => (float) str_replace('.', '', ($row[22] ?? 0)),
            'biaya' => (float) str_replace('.', '', ($row[23] ?? 0)),
            'efektifitas_iklan' => ($row[24] ?? 0),
            'efektivitas_langsung' => ($row[25] ?? 0),
            'acos' => ($row[26] ?? 0),
            'acos_langsung' => ($row[27] ?? 0),
            'jumlah_produk_dilihat' => (int) ($row[28] ?? 0),
            'jumlah_klik_produk' => (int) ($row[29] ?? 0),
            'persentase_klik_produk' => ($row[30] ?? 0),
            'user_id' => $this->userId,
            'username' => $this->username,
            'bulan' => $this->bulan,
            'tahun' => $this->tahun
        ]);
    }
}
