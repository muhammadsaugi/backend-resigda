<?php

namespace App\Http\Controllers;

use App\Models\CivicAggregation;
use App\Models\Regulation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\CivicInteraction;
use App\Models\ClaimVerification;

class CivicIntelligenceController extends Controller
{
    /**
     * GET /api/admin/civic-insights
     * Data untuk Dasbor Suara Warga: tren topik, sentimen dominan,
     * regulasi paling banyak ditanyakan. Dibaca dari civic_aggregations
     * dengan real-time fallback dari civic_interactions jika belum ada agregasi.
     */
    public function civicInsights(Request $request): JsonResponse
    {
        // 1. Ambil data real-time dari CivicInteraction (Chat Tanya REGS)
        $interactionsQuery = CivicInteraction::query();
        if ($request->filled('kecamatan')) {
            $interactionsQuery->where('kecamatan', $request->string('kecamatan'));
        }
        $interactions = $interactionsQuery->get();

        // 2. Ambil data real-time dari ClaimVerification (Verifikasi Klaim & Usulan)
        $claimsQuery = \App\Models\ClaimVerification::query();
        if ($request->filled('kecamatan')) {
            $claimsQuery->where('kecamatan', $request->string('kecamatan'));
        }
        $claims = $claimsQuery->get();

        // Total Interaksi Real-Time
        $totalInteraksi = $interactions->count() + $claims->count();

        // Topik Terbanyak Real-Time
        $topicsMap = [];
        foreach ($interactions as $i) {
            $t = $i->topic ?: 'Umum / Informasi Regulasi';
            $topicsMap[$t] = ($topicsMap[$t] ?? 0) + 1;
        }
        foreach ($claims as $c) {
            $t = $c->layanan ? "Layanan {$c->layanan}" : ($c->kategori_laporan === 'usul_regulasi' ? 'Usulan Regulasi Baru' : 'Verifikasi Klaim Petugas');
            $topicsMap[$t] = ($topicsMap[$t] ?? 0) + 1;
        }

        $topikTerbanyak = collect($topicsMap)
            ->map(fn ($count, $topic) => ['topic' => $topic, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->take(10);

        // Breakdown Sentimen Real-Time
        $posCount = $interactions->whereIn('sentiment', ['positif', 'informasi'])->count() + $claims->where('hasil_verifikasi', 'ditemukan')->count();
        $neuCount = $interactions->whereIn('sentiment', ['netral', null, ''])->count() + $claims->where('hasil_verifikasi', 'sebagian_sesuai')->count();
        $negCount = $interactions->whereIn('sentiment', ['negatif', 'keluhan', 'indikasi_pungli'])->count() + $claims->whereIn('hasil_verifikasi', ['tidak_ditemukan'])->count();

        $totalSentimenCount = ($posCount + $neuCount + $negCount) ?: 1;

        $sentimenBreakdown = [
            'positive' => round(($posCount / $totalSentimenCount) * 100),
            'neutral' => round(($neuCount / $totalSentimenCount) * 100),
            'negative' => round(($negCount / $totalSentimenCount) * 100),
        ];

        // Regulasi Paling Banyak Ditanyakan
        $regulasiPalingDitanyakan = Regulation::orderByDesc('jumlah_ditanyakan')
            ->take(10)
            ->get(['id', 'judul', 'jenis', 'nomor', 'tahun', 'jumlah_ditanyakan']);

        // Usulan Regulasi Baru dari Warga
        $usulanRegulasiBaru = \App\Models\ClaimVerification::where('kategori_laporan', 'usul_regulasi')
            ->orderByDesc('created_at')
            ->take(15)
            ->get(['id', 'klaim_text', 'catatan_laporan', 'kecamatan', 'layanan', 'created_at']);

        return response()->json([
            'total_interaksi' => $totalInteraksi,
            'topik_terbanyak' => $topikTerbanyak,
            'sentimen_breakdown' => $sentimenBreakdown,
            'regulasi_paling_ditanyakan' => $regulasiPalingDitanyakan,
            'usulan_regulasi_baru' => $usulanRegulasiBaru,
        ]);
    }

    /**
     * GET /api/admin/decay
     * Regulatory Decay Tracker: daftar regulasi berdasar decay_score,
     * plus status closed-loop revisi terbaru kalau ada.
     */
    public function decay(Request $request): JsonResponse
    {
        $minScore = (float) $request->input('min_score', 0);

        $regulations = Regulation::with(['latestRevisionTracking.history', 'latestRevisionTracking.assignee'])
            ->where('decay_score', '>=', $minScore)
            ->orderByDesc('decay_score')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json($regulations);
    }

    /**
     * GET /api/public/stats
     * Civic Pulse aggregate stats for homepage.
     */
    public function publicStats(): JsonResponse
    {
        $currentMonthStart = now()->startOfMonth();

        $pertanyaanBulanIni = CivicInteraction::where('created_at', '>=', $currentMonthStart)->count();
        if ($pertanyaanBulanIni === 0) {
            $pertanyaanBulanIni = CivicInteraction::count();
        }

        $topTopicGroup = CivicInteraction::select('topic', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->whereNotNull('topic')
            ->groupBy('topic')
            ->orderByDesc('count')
            ->first();

        $topikTerbanyak = $topTopicGroup ? ucwords(str_replace('_', ' ', $topTopicGroup->topic)) : 'Pajak & Retribusi';
        $totalTopicCount = CivicInteraction::whereNotNull('topic')->count() ?: 1;
        $persentaseTopik = $topTopicGroup ? round(($topTopicGroup->count / $totalTopicCount) * 100) : 35;

        $klaimDiverifikasi = ClaimVerification::count();
        $indikasiPungli = ClaimVerification::where('dilaporkan_ke_inspektorat', true)->count();
        $regulasiTerindeks = Regulation::count();

        return response()->json([
            'pertanyaan_bulan_ini' => $pertanyaanBulanIni,
            'topik_terbanyak' => $topikTerbanyak,
            'persentase_topik' => $persentaseTopik,
            'klaim_diverifikasi' => $klaimDiverifikasi,
            'indikasi_pungli_dilaporkan' => $indikasiPungli,
            'regulasi_terindeks' => $regulasiTerindeks,
            'periode' => now()->translatedFormat('F Y'),
        ]);
    }
}