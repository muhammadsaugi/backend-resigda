<?php

namespace App\Console\Commands;

use App\Models\CivicAggregation;
use App\Models\CivicInteraction;
use App\Models\Regulation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dijadwalkan setiap pukul 02.00 WIB (lihat routes/console.php).
 * Sesuai arsitektur di master prompt:
 *   1. Agregasi civic_interactions -> civic_aggregations
 *   2. Hapus data mentah yang sudah > 30 hari
 *   3. Update jumlah_ditanyakan per regulasi
 */
class AggregateInteractions extends Command
{
    protected $signature = 'regsida:aggregate-interactions';

    protected $description = 'Agregasi civic_interactions harian ke civic_aggregations, lalu hapus data mentah lama';

    public function handle(): int
    {
        $this->info('Memulai agregasi civic_interactions...');

        // Kelompokkan interaksi per bulan + topic + sentiment + kecamatan
        $grouped = CivicInteraction::query()
            ->whereNotNull('topic')
            ->whereNotNull('sentiment')
            ->select([
                DB::raw("date_trunc('month', interacted_at) as periode"),
                'topic',
                'sentiment',
                'kecamatan',
                DB::raw('count(*) as jumlah'),
            ])
            ->groupBy('periode', 'topic', 'sentiment', 'kecamatan')
            ->get();

        $regulationIdCounter = [];

        foreach ($grouped as $row) {
            // Upsert: kalau bucket periode+topic+sentiment+kecamatan sudah ada, tambahkan jumlahnya
            $existing = CivicAggregation::where('periode', $row->periode)
                ->where('topic', $row->topic)
                ->where('sentiment', $row->sentiment)
                ->where('kecamatan', $row->kecamatan)
                ->first();

            if ($existing) {
                $existing->increment('jumlah_interaksi', $row->jumlah);
            } else {
                CivicAggregation::create([
                    'periode' => $row->periode,
                    'topic' => $row->topic,
                    'sentiment' => $row->sentiment,
                    'kecamatan' => $row->kecamatan,
                    'jumlah_interaksi' => $row->jumlah,
                ]);
            }
        }

        // Update jumlah_ditanyakan per regulasi dari regulation_ids di civic_interactions
        CivicInteraction::query()->whereNotNull('regulation_ids')->chunk(500, function ($interactions) use (&$regulationIdCounter) {
            foreach ($interactions as $interaction) {
                foreach ($interaction->regulation_ids ?? [] as $regId) {
                    $regulationIdCounter[$regId] = ($regulationIdCounter[$regId] ?? 0) + 1;
                }
            }
        });

        foreach ($regulationIdCounter as $regulationId => $count) {
            Regulation::where('id', $regulationId)->increment('jumlah_ditanyakan', $count);
        }

        // Hapus data mentah > 30 hari (privacy by design — retensi terbatas)
        $deleted = CivicInteraction::where('interacted_at', '<', now()->subDays(30))->delete();

        $this->info("Agregasi selesai. {$grouped->count()} bucket diproses, {$deleted} baris mentah lama dihapus.");

        return self::SUCCESS;
    }
}