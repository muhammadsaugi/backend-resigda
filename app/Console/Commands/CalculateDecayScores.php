<?php

namespace App\Console\Commands;

use App\Models\Regulation;
use App\Models\RevisionHistory;
use App\Models\RevisionTracking;
use App\Services\DecayScoreService;
use Illuminate\Console\Command;

/**
 * Dijadwalkan harian (lihat routes/console.php) dan juga bisa dipicu manual
 * lewat POST /api/admin/decay/recalculate (tombol "Hitung Ulang" di UI).
 * Menghitung ulang decay_score semua regulasi berlaku dari faktor nyata
 * (lihat DecayScoreService), lalu regulasi yang skornya melewati ambang
 * otomatis dimasukkan ke antrean closed-loop revisi ("terdeteksi") kalau
 * belum ada proses revisi yang sedang berjalan untuknya.
 */
class CalculateDecayScores extends Command
{
    protected $signature = 'regsida:calculate-decay {--threshold=70 : Ambang skor untuk otomatis masuk antrean revisi}';

    protected $description = 'Hitung ulang Regulatory Decay Score dari Siklus Evaluasi (RIA), Hambatan Informasi Publik, dan Ambiguitas Semantik AI';

    public function handle(DecayScoreService $service): int
    {
        $threshold = (float) $this->option('threshold');
        $regulations = Regulation::where('status', 'berlaku')->get();
        $masukAntrean = 0;

        foreach ($regulations as $regulation) {
            $result = $service->calculate($regulation);
            $regulation->update(['decay_score' => $result['total']]);

            if ($result['total'] < $threshold) {
                continue;
            }

            $latest = $regulation->latestRevisionTracking;
            $sudahBerjalan = $latest && $latest->status !== 'selesai';

            if ($sudahBerjalan) {
                continue;
            }

            $catatan = sprintf(
                'Terdeteksi otomatis oleh Regulatory Decay Tracker — Skor %s (Siklus Evaluasi: %s, Hambatan Publik: %s, Ambiguitas AI: %s).',
                $result['total'],
                $result['faktor_usia']['skor'],
                $result['faktor_frekuensi']['skor'],
                $result['faktor_confidence']['skor'],
            );

            $tracking = RevisionTracking::create([
                'regulation_id' => $regulation->id,
                'status' => 'terdeteksi',
                'catatan' => $catatan,
            ]);

            RevisionHistory::create([
                'revision_tracking_id' => $tracking->id,
                'status' => 'terdeteksi',
                'catatan' => $catatan,
            ]);

            $masukAntrean++;
        }

        $this->info("Decay score dihitung ulang untuk {$regulations->count()} regulasi. {$masukAntrean} regulasi baru masuk antrean closed-loop revisi.");

        return self::SUCCESS;
    }
}
