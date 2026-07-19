<?php

namespace App\Http\Controllers;

use App\Models\CivicAggregation;
use App\Models\ClaimVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspektoratController extends Controller
{
    /**
     * GET /api/admin/inspektorat/pungli-heatmap
     * Role: inspektorat saja.
     *
     * Gabungkan 2 sumber sinyal indikasi pungli:
     *  1. claim_verifications dengan hasil "tidak_ditemukan" — warga aktif
     *     memverifikasi klaim petugas dan tidak ada dasar hukumnya
     *  2. civic_aggregations dengan sentiment "indikasi_pungli" — sinyal
     *     dari chat Tanya REGS yang terklasifikasi otomatis
     *
     * skor_risiko = penjumlahan sederhana kedua sinyal, per kecamatan.
     * Bukan skor statistik canggih — cukup untuk memprioritaskan kecamatan
     * mana yang perlu ditinjau duluan oleh Inspektorat.
     */
    public function pungliHeatmap(Request $request): JsonResponse
    {
        $fromClaims = ClaimVerification::selectRaw(
            'kecamatan, count(*) as total_klaim, ' .
            "sum(case when hasil_verifikasi = 'tidak_ditemukan' then 1 else 0 end) as klaim_tanpa_dasar_hukum, " .
            "sum(case when hasil_verifikasi = 'sebagian_sesuai' then 1 else 0 end) as klaim_sebagian_sesuai"
        )
            ->whereNotNull('kecamatan')
            ->groupBy('kecamatan')
            ->get()
            ->keyBy('kecamatan');

        $fromChat = CivicAggregation::where('sentiment', 'indikasi_pungli')
            ->whereNotNull('kecamatan')
            ->selectRaw('kecamatan, sum(jumlah_interaksi) as jumlah_indikasi_pungli_chat')
            ->groupBy('kecamatan')
            ->get()
            ->keyBy('kecamatan');

        $semuaKecamatan = $fromClaims->keys()->merge($fromChat->keys())->unique();

        $heatmap = $semuaKecamatan->map(function ($kecamatan) use ($fromClaims, $fromChat) {
            $claim = $fromClaims->get($kecamatan);
            $chat = $fromChat->get($kecamatan);

            $klaimTanpaDasar = $claim->klaim_tanpa_dasar_hukum ?? 0;
            $indikasiChat = $chat->jumlah_indikasi_pungli_chat ?? 0;

            return [
                'kecamatan' => $kecamatan,
                'total_klaim_diverifikasi' => $claim->total_klaim ?? 0,
                'klaim_tanpa_dasar_hukum' => $klaimTanpaDasar,
                'klaim_sebagian_sesuai' => $claim->klaim_sebagian_sesuai ?? 0,
                'indikasi_pungli_dari_chat' => $indikasiChat,
                'skor_risiko' => $klaimTanpaDasar + $indikasiChat,
            ];
        })->sortByDesc('skor_risiko')->values();

        return response()->json($heatmap);
    }

    /**
     * GET /api/admin/inspektorat/claim-history
     * Role: inspektorat saja. Riwayat lengkap verifikasi klaim untuk investigasi
     * lanjutan — beda dengan civic_interactions (chat pasif), claim_verifications
     * memang menyimpan klaim_text karena ini fitur verifikasi aktif yang warga
     * sengaja ajukan untuk dicek (lihat catatan privacy di migration terkait).
     *
     * Filter opsional: ?layanan=IMB, ?kecamatan=..., ?hasil_verifikasi=tidak_ditemukan
     */
    public function claimHistory(Request $request): JsonResponse
    {
        $query = ClaimVerification::query();

        if ($request->filled('layanan')) {
            $query->where('layanan', $request->string('layanan'));
        }
        if ($request->filled('kecamatan')) {
            $query->where('kecamatan', $request->string('kecamatan'));
        }
        if ($request->filled('hasil_verifikasi')) {
            $query->where('hasil_verifikasi', $request->string('hasil_verifikasi'));
        }

        $claims = $query->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($claims);
    }
}