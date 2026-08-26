<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sesuai arsitektur "Alur agregasi malam hari" di master prompt.
// Timezone WIB (Asia/Jakarta) di-set eksplisit karena server produksi
// (Railway/Render) biasanya default UTC.
Schedule::command('regsida:aggregate-interactions')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta');

// Dijalankan setelah agregasi interaksi (03:00) supaya jumlah_ditanyakan
// per regulasi sudah ter-update lebih dulu sebelum decay score dihitung ulang.
Schedule::command('regsida:calculate-decay')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta');