<?php

namespace App\Http\Controllers;

use App\Models\ClaimVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifikasiKlaimController extends Controller
{
    /**
     * GET /api/admin/verifikasi-klaim
     * Dasbor monitoring khusus untuk semua laporan warga dari fitur Verifikasi Klaim
     * (baik pengaduan indikasi pungli maupun usulan regulasi baru), terpisah dari
     * agregat analitik Suara Warga agar tiap laporan mudah ditelusuri satu per satu.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kategori_laporan' => ['nullable', 'in:pungli_petugas,usul_regulasi'],
            'hasil_verifikasi' => ['nullable', 'in:ditemukan,tidak_ditemukan,sebagian_sesuai'],
            'status_audit' => ['nullable', 'in:baru,proses_audit,selesai'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $baseQuery = ClaimVerification::query()->where('dilaporkan_ke_inspektorat', true);

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'pungli_petugas' => (clone $baseQuery)->where('kategori_laporan', 'pungli_petugas')->count(),
            'usul_regulasi' => (clone $baseQuery)->where('kategori_laporan', 'usul_regulasi')->count(),
            'belum_ditindak' => (clone $baseQuery)->where('status_audit', 'baru')->count(),
        ];

        $claims = $baseQuery
            ->when($validated['kategori_laporan'] ?? null, fn ($q, $v) => $q->where('kategori_laporan', $v))
            ->when($validated['hasil_verifikasi'] ?? null, fn ($q, $v) => $q->where('hasil_verifikasi', $v))
            ->when($validated['status_audit'] ?? null, fn ($q, $v) => $q->where('status_audit', $v))
            ->when($validated['kecamatan'] ?? null, fn ($q, $v) => $q->where('kecamatan', $v))
            ->latest()
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return response()->json([
            'summary' => $summary,
            'data' => $claims->items(),
            'total' => $claims->total(),
            'current_page' => $claims->currentPage(),
            'last_page' => $claims->lastPage(),
        ]);
    }
}
