<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PerformaIklanImport;

class ProcessPerformaIklan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $username,
        public int $userId,
        public int $bulan,
        public int $tahun,
        public string $filePath
    ) {}

    public function handle()
    {
        Excel::import(
            new PerformaIklanImport(
                $this->username,
                $this->userId,
                $this->bulan,
                $this->tahun
            ),
            storage_path("app/public/{$this->filePath}")
        );
    }
}