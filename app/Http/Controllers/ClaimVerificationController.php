<?php

namespace App\Http\Controllers;

use App\Models\ClaimVerification;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ClaimVerificationController extends Controller
{
    public function __construct(protected AIService $aiService)
    {
    }

    /**
     * POST /api/verify-claim
     * Warga masukkan klaim petugas (mis. "katanya bikin IMB kena biaya 500rb
     * di luar loket"), sistem cek ke regulasi resmi apakah klaim itu didukung
     * dasar hukum. Ini pakai endpoint /rag yang sama di FastAPI — bedanya
     * di sini kita interpretasikan hasilnya jadi status ditemukan/tidak.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'klaim_text' => ['required', 'string', 'min:5', 'max:1000'],
            'layanan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->aiService->askRag($validated['klaim_text']);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Gagal memverifikasi klaim. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 502);
        }

        $regulationIds = collect($result['sources'] ?? [])->pluck('regulation_id')->unique()->values()->all();
        $overallConfidence = $result['confidence'] ?? 0;

        // Ambang batas confidence untuk menentukan status. Threshold ini
        // pilihan desain awal, boleh disesuaikan setelah lihat data nyata
        // seberapa akurat pgvector top-K match untuk skenario verifikasi klaim.
        $hasilVerifikasi = match (true) {
            $overallConfidence >= 0.75 => 'ditemukan',
            $overallConfidence >= 0.45 => 'sebagian_sesuai',
            default => 'tidak_ditemukan',
        };

        $claim = ClaimVerification::create([
            'session_id' => $validated['session_id'],
            'citizen_id' => $request->user('sanctum')?->id,
            'klaim_text' => $validated['klaim_text'],
            'hasil_verifikasi' => $hasilVerifikasi,
            'regulation_ids' => $regulationIds,
            'kecamatan' => $validated['kecamatan'] ?? null,
            'layanan' => $validated['layanan'] ?? null,
        ]);

        return response()->json([
            'id' => $claim->id,
            'hasil_verifikasi' => $hasilVerifikasi,
            'answer' => $result['answer'],
            'sources' => $result['sources'],
            'confidence' => $overallConfidence,
            // CTA ke SP4N-LAPOR! kalau tidak ditemukan dasar hukumnya,
            // sesuai fitur "Verifikasi Klaim" di master prompt
            'show_lapor_cta' => $hasilVerifikasi === 'tidak_ditemukan',
        ]);
    }
}