<?php

namespace App\Http\Controllers;

use App\Models\CivicAggregation;
use App\Models\Regulation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CivicIntelligenceController extends Controller
{
    /**
     * GET /api/admin/civic-insights
     * Data untuk Dasbor Suara Warga: tren topik, sentimen dominan,
     * regulasi paling banyak ditanyakan. Dibaca dari civic_aggregations
     * (hasil job malam hari), BUKAN dari civic_interactions mentah,
     * supaya query cepat dan tidak membuka celah re-identifikasi individual.
     *
     * Query params opsional:
     *   ?bulan=2026-07   → filter periode tertentu (default: 6 bulan terakhir)
     *   ?kecamatan=...   → filter kecamatan
     */
    public function civicInsights(Request $request): JsonResponse
    {
        $query = CivicAggregation::query();

        if ($request->filled('bulan')) {
            $query->whereRaw("to_char(periode, 'YYYY-MM') = ?", [$request->string('bulan')]);
        } else {
            $query->where('periode', '>=', now()->subMonths(6)->startOfMonth());
        }

        if ($request->filled('kecamatan')) {
            $query->where('kecamatan', $request->string('kecamatan'));
        }

        $aggregations = $query->orderBy('periode')->get();

        $trenPerBulan = $aggregations
            ->groupBy(fn ($row) => $row->periode->format('Y-m'))
            ->map(fn ($rows) => $rows->sum('jumlah_interaksi'));

        $topikTerbanyak = $aggregations
            ->groupBy('topic')
            ->map(fn ($rows) => $rows->sum('jumlah_interaksi'))
            ->sortDesc()
            ->take(10);

        $sentimenDominan = $aggregations
            ->groupBy('sentiment')
            ->map(fn ($rows) => $rows->sum('jumlah_interaksi'))
            ->sortDesc();

        // Regulasi paling banyak ditanyakan — pakai kolom jumlah_ditanyakan
        // di tabel regulations (di-update oleh job agregasi, lihat AggregateInteractions)
        $regulasiPalingDitanyakan = Regulation::orderByDesc('jumlah_ditanyakan')
            ->take(10)
            ->get(['id', 'judul', 'jenis', 'nomor', 'tahun', 'jumlah_ditanyakan']);

        return response()->json([
            'tren_per_bulan' => $trenPerBulan,
            'topik_terbanyak' => $topikTerbanyak,
            'sentimen_dominan' => $sentimenDominan,
            'regulasi_paling_ditanyakan' => $regulasiPalingDitanyakan,
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
}