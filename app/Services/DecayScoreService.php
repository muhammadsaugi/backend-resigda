<?php

namespace App\Services;

use App\Models\CivicInteraction;
use App\Models\Regulation;

/**
 * Menghitung Regulatory Decay Score dari 3 Indikator Kebutuhan Peninjauan Hukum (0 - 100):
 * 1. Siklus Evaluasi Hukum (Review Window) — Max 40 Poin: Sesuai UU No. 12/2011 jo. UU No. 13/2022 (prinsip RIA),
 *    regulasi yang berusia >5 tahun wajib masuk jendela evaluasi berkala untuk sinkronisasi hukum.
 * 2. Hambatan Informasi Publik (Information Friction Index) — Max 30 Poin: Tingginya volume pertanyaan warga
 *    menandakan regulasi membutuhkan klarifikasi, aturan pelaksanaan (Juknis), atau sosialisasi ulang.
 * 3. Ambiguitas Semantik AI (Normative Ambiguity Index) — Max 30 Poin: Diukur dari RAG vector cosine similarity
 *    (confidence score). Kepastian AI yang rendah mengindikasikan norma pasal bersifat abstrak atau multitafsir.
 */
class DecayScoreService
{
    /** Poin maksimum tiap faktor — totalnya 100. */
    private const MAX_SKOR_USIA = 40;
    private const MAX_SKOR_FREKUENSI = 30;
    private const MAX_SKOR_CONFIDENCE = 30;

    /** Batas ideal siklus evaluasi berkala (tahun) berdasarkan prinsip RIA / UU 12/2011. */
    private const SIKLUS_EVALUASI_TAHUN = 5;

    /** Jumlah pertanyaan batas maksimum indikator hambatan informasi publik. */
    private const FREKUENSI_MAKSIMUM = 60;

    public function calculate(Regulation $regulation): array
    {
        $usiaTahun = $regulation->tanggal_terbit
            ? max(0, (int) $regulation->tanggal_terbit->diffInYears(now()))
            : 0;
        
        // Siklus Evaluasi Hukum: Mencapai skor maksimum jika usia >= 5 tahun
        $skorUsia = round(min(self::MAX_SKOR_USIA, ($usiaTahun / self::SIKLUS_EVALUASI_TAHUN) * self::MAX_SKOR_USIA), 2);

        $jumlahDitanyakan = $regulation->jumlah_ditanyakan;
        $skorFrekuensi = round(min(self::MAX_SKOR_FREKUENSI, ($jumlahDitanyakan / self::FREKUENSI_MAKSIMUM) * self::MAX_SKOR_FREKUENSI), 2);

        // Rata-rata confidence AI saat memetakan pertanyaan warga ke pasal regulasi (RAG vector distance).
        // Makin rendah confidence (high distance), makin besar indikasi norma pasal abstrak/multitafsir.
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
                'nama' => 'Siklus Evaluasi Hukum (Review Window)',
                'keterangan' => "Usia {$usiaTahun} tahun sejak diundangkan. Sesuai prinsip RIA (UU 12/2011), regulasi berusia " . self::SIKLUS_EVALUASI_TAHUN . "+ tahun memerlukan evaluasi berkala untuk sinkronisasi.",
            ],
            'faktor_frekuensi' => [
                'skor' => $skorFrekuensi,
                'maksimum' => self::MAX_SKOR_FREKUENSI,
                'jumlah_ditanyakan' => $jumlahDitanyakan,
                'nama' => 'Hambatan Informasi Publik (Friction Index)',
                'keterangan' => "Ditanyakan {$jumlahDitanyakan} kali di Tanya REGS. Volume pertanyaan tinggi menandakan perlunya sosialisasi atau Juknis aturan pelaksanaan.",
            ],
            'faktor_confidence' => [
                'skor' => $skorConfidence,
                'maksimum' => self::MAX_SKOR_CONFIDENCE,
                'rata_rata_confidence_ai' => $avgConfidence !== null ? round((float) $avgConfidence, 4) : null,
                'nama' => 'Ambiguitas Semantik AI (Normative Ambiguity Index)',
                'keterangan' => $avgConfidence !== null
                    ? 'Rata-rata kepastian AI saat memetakan pasal ke kasus warga: ' . round($avgConfidence * 100) . '%. Skor kepastian rendah menandakan norma pasal bersifat abstrak atau multitafsir.'
                    : 'Belum ada interaksi warga yang mencocokkan regulasi ini sebagai rujukan utama.',
            ],
        ];
    }
}

