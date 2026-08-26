<?php

namespace App\Http\Controllers;

use App\Models\CivicInteraction;
use App\Models\ClaimVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspektoratController extends Controller
{
    /** Ringkasan risiko indikasi pungli per kecamatan. */
    public function pungliHeatmap(): JsonResponse
    {
        $claims = ClaimVerification::query()
            ->whereNotNull('kecamatan')
            ->get(['kecamatan', 'hasil_verifikasi']);

        $chatCounts = CivicInteraction::query()
            ->where('sentiment', 'indikasi_pungli')
            ->whereNotNull('kecamatan')
            ->selectRaw('kecamatan, COUNT(*) as total')
            ->groupBy('kecamatan')
            ->pluck('total', 'kecamatan');

        $rows = $claims->groupBy('kecamatan')->map(function ($items, string $kecamatan) use ($chatCounts) {
            $tanpaDasar = $items->where('hasil_verifikasi', 'tidak_ditemukan')->count();
            $sebagianSesuai = $items->where('hasil_verifikasi', 'sebagian_sesuai')->count();
            $indikasiChat = (int) ($chatCounts[$kecamatan] ?? 0);

            return [
                'kecamatan' => $kecamatan,
                'total_klaim_diverifikasi' => $items->count(),
                'klaim_tanpa_dasar_hukum' => $tanpaDasar,
                'klaim_sebagian_sesuai' => $sebagianSesuai,
                'indikasi_pungli_dari_chat' => $indikasiChat,
                'skor_risiko' => ($tanpaDasar * 3) + ($sebagianSesuai * 2) + $indikasiChat,
            ];
        });

        foreach ($chatCounts as $kecamatan => $total) {
            if (! $rows->has($kecamatan)) {
                $rows->put($kecamatan, [
                    'kecamatan' => $kecamatan,
                    'total_klaim_diverifikasi' => 0,
                    'klaim_tanpa_dasar_hukum' => 0,
                    'klaim_sebagian_sesuai' => 0,
                    'indikasi_pungli_dari_chat' => (int) $total,
                    'skor_risiko' => (int) $total,
                ]);
            }
        }

        return response()->json($rows->sortByDesc('skor_risiko')->values());
    }

    /** Riwayat laporan warga yang diteruskan ke Inspektorat. */
    public function claimHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'layanan' => ['nullable', 'string', 'max:100'],
            'hasil_verifikasi' => ['nullable', 'in:ditemukan,tidak_ditemukan,sebagian_sesuai'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $claims = ClaimVerification::query()
            ->where('dilaporkan_ke_inspektorat', true)
            ->where('kategori_laporan', 'pungli_petugas')
            ->when($validated['kecamatan'] ?? null, fn ($query, $value) => $query->where('kecamatan', $value))
            ->when($validated['layanan'] ?? null, fn ($query, $value) => $query->where('layanan', $value))
            ->when($validated['hasil_verifikasi'] ?? null, fn ($query, $value) => $query->where('hasil_verifikasi', $value))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($claims);
    }

    /** Ubah progres penanganan laporan: baru -> proses_audit -> selesai. */
    public function updateClaimStatus(Request $request, ClaimVerification $claimVerification): JsonResponse
    {
        $validated = $request->validate([
            'status_audit' => ['required', 'in:baru,proses_audit,selesai'],
        ]);

        abort_unless($claimVerification->dilaporkan_ke_inspektorat, 404);

        $claimVerification->update($validated);

        return response()->json($claimVerification->fresh());
    }
}
