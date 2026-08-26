<?php

namespace App\Services;

use App\Models\CivicInteraction;
use App\Models\Regulation;

/**
 * Menghitung Regulatory Decay Score dari 3 faktor NYATA yang sudah ada di
 * skema (bukan angka statis) — sesuai klaim yang ditampilkan di UI Decay
 * Tracker: usia regulasi, frekuensi pertanyaan warga, dan tingkat
 * kepastian AI saat menjawab. Dipakai bareng oleh command
 * `regsida:calculate-decay` (batch, dijadwalkan) dan endpoint admin
 * (breakdown transparan per regulasi, untuk panel penjelasan di UI).
 */
class DecayScoreService
{
    /** Poin maksimum tiap faktor — totalnya 100. */
    private const MAX_SKOR_USIA = 40;
    private const MAX_SKOR_FREKUENSI = 30;
    private const MAX_SKOR_CONFIDENCE = 30;

    /** Usia (tahun) di mana faktor usia sudah mencapai skor maksimum. */
    private const USIA_MAKSIMUM_TAHUN = 10;

    /** Jumlah pertanyaan di mana faktor frekuensi sudah mencapai skor maksimum. */
    private const FREKUENSI_MAKSIMUM = 60;

    public function calculate(Regulation $regulation): array
    {
        $usiaTahun = $regulation->tanggal_terbit
            ? max(0, (int) $regulation->tanggal_terbit->diffInYears(now()))
            : 0;
        $skorUsia = round(min(self::MAX_SKOR_USIA, ($usiaTahun / self::USIA_MAKSIMUM_TAHUN) * self::MAX_SKOR_USIA), 2);

        $jumlahDitanyakan = $regulation->jumlah_ditanyakan;
        $skorFrekuensi = round(min(self::MAX_SKOR_FREKUENSI, ($jumlahDitanyakan / self::FREKUENSI_MAKSIMUM) * self::MAX_SKOR_FREKUENSI), 2);

        // Rata-rata confidence AI saat menjawab pertanyaan warga yang bersumber
        // dari regulasi ini (civic_interactions.regulation_ids berisi array id
        // regulasi yang dipakai RAG untuk menjawab). Makin RENDAH confidence-nya,
        // makin besar sinyal bahwa regulasi ini ambigu/sulit dijadikan rujukan
        // AI secara pasti -> makin besar kontribusinya ke decay score.
        $avgConfidence = CivicInteraction::query()
            ->whereRaw('regulation_ids::jsonb @> ?', [json_encode([$regulation->id])])
            ->whereNotNull('confidence_score')
            ->avg('confidence_score');

        $skorConfidence = $avgConfidence !== null
            ? round((1 - (float) $avgConfidence) * self::MAX_SKOR_CONFIDENCE, 2)
            : 0.0;

        $total = min(100, round($skorUsia + $skorFrekuensi + $skorConfidence, 2));

        return [
            'total' => $total,
            'faktor_usia' => [
                'skor' => $skorUsia,
                'maksimum' => self::MAX_SKOR_USIA,
                'usia_tahun' => $usiaTahun,
                'keterangan' => "Usia {$usiaTahun} tahun sejak tanggal terbit (skor maksimum di usia " . self::USIA_MAKSIMUM_TAHUN . " tahun ke atas).",
            ],
            'faktor_frekuensi' => [
                'skor' => $skorFrekuensi,
                'maksimum' => self::MAX_SKOR_FREKUENSI,
                'jumlah_ditanyakan' => $jumlahDitanyakan,
                'keterangan' => "Ditanyakan warga/ASN sebanyak {$jumlahDitanyakan} kali lewat Tanya REGS (skor maksimum di " . self::FREKUENSI_MAKSIMUM . "x pertanyaan ke atas).",
            ],
            'faktor_confidence' => [
                'skor' => $skorConfidence,
                'maksimum' => self::MAX_SKOR_CONFIDENCE,
                'rata_rata_confidence_ai' => $avgConfidence !== null ? round((float) $avgConfidence, 4) : null,
                'keterangan' => $avgConfidence !== null
                    ? 'Rata-rata keyakinan AI saat menjawab pertanyaan yang bersumber dari regulasi ini: ' . round($avgConfidence * 100) . '% (makin rendah, makin besar kontribusi skor decay).'
                    : 'Belum ada interaksi warga yang tercatat memakai regulasi ini sebagai sumber jawaban.',
            ],
        ];
    }
}
