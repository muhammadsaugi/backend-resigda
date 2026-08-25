<?php

namespace App\Http\Controllers;

use App\Models\ClaimVerification;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClaimVerificationController extends Controller
{
    public function __construct(protected AIService $aiService)
    {
    }

    /**
     * POST /api/verify-claim
     * Warga masukkan klaim petugas (mis. "katanya bikin IMB kena biaya 500rb
     * di luar loket"), sistem cek ke regulasi resmi apakah klaim itu didukung
     * dasar hukum. Mendukung upload foto_bukti anonim dengan pembersihan EXIF GPS.
     */
    public function store(Request $request): JsonResponse
    {
        $citizenUser = $request->user('sanctum');
        $citizenId = $citizenUser instanceof \App\Models\Citizen ? $citizenUser->id : null;

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'klaim_text' => ['required', 'string', 'min:5', 'max:500'],
            'layanan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'foto_bukti' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $fotoBuktiUrl = null;
        if ($request->hasFile('foto_bukti')) {
            $file = $request->file('foto_bukti');
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = 'bukti_' . Str::uuid() . '.' . $extension;
            $path = $file->storeAs('bukti_klaim', $filename, 'public');

            // Strip EXIF metadata if image is JPEG/JPG to protect whistleblower GPS/camera identity
            $fullPath = Storage::disk('public')->path($path);
            if (in_array($extension, ['jpg', 'jpeg']) && function_exists('imagecreatefromjpeg')) {
                @$img = imagecreatefromjpeg($fullPath);
                if ($img) {
                    imagejpeg($img, $fullPath, 90);
                    imagedestroy($img);
                }
            }

            $fotoBuktiUrl = Storage::url($path);
        }

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

        $hasilVerifikasi = match (true) {
            $overallConfidence >= 0.75 => 'ditemukan',
            $overallConfidence >= 0.45 => 'sebagian_sesuai',
            default => 'tidak_ditemukan',
        };

        $claim = ClaimVerification::create([
            'session_id' => $validated['session_id'],
            'citizen_id' => $citizenId,
            'klaim_text' => $validated['klaim_text'],
            'foto_bukti' => $fotoBuktiUrl,
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
            'foto_bukti' => $fotoBuktiUrl,
            'show_lapor_cta' => $hasilVerifikasi !== 'ditemukan',
        ]);
    }

    /**
     * POST /api/verify-claim/{claimVerification}/report
     * Laporkan klaim tanpa dasar hukum / indikasi pungli langsung ke Inspektorat.
     * Mengizinkan pelaporan anonim jika foto_bukti disertakan atau is_anonymous = true.
     */
    public function reportToInspektorat(Request $request, ClaimVerification $claimVerification): JsonResponse
    {
        $citizenUser = $request->user('sanctum');
        $isAnonymous = $request->boolean('is_anonymous');
        $hasFotoBukti = ! empty($claimVerification->foto_bukti);

        // Jika tidak login, tidak punya foto bukti, dan tidak mencentang anonim -> kembalikan 401
        if (! ($citizenUser instanceof \App\Models\Citizen) && ! $hasFotoBukti && ! $isAnonymous) {
            return response()->json([
                'message' => 'Silakan Login/Register atau sertakan Foto Bukti / centang Laporan Anonim untuk mengirimkan pengaduan.',
            ], 401);
        }

        $validated = $request->validate([
            'catatan_laporan' => ['nullable', 'string', 'max:500'],
            'kategori_laporan' => ['nullable', 'in:pungli_petugas,usul_regulasi'],
        ]);

        $kategori = $validated['kategori_laporan'] ?? 'pungli_petugas';
        $prefix = $hasFotoBukti ? '[DILENGKAPI FOTO BUKTI ANONIM] ' : '';
        $defaultNote = $kategori === 'usul_regulasi'
            ? $prefix . 'Usulan/aspirasi kebutuhan regulasi baru oleh warga karena belum diatur.'
            : $prefix . 'Pengaduan dugaan pungli / klaim layanan tidak sesuai regulasi oleh warga.';

        $claimVerification->update([
            'dilaporkan_ke_inspektorat' => true,
            'kategori_laporan' => $kategori,
            'catatan_laporan' => $validated['catatan_laporan'] ? ($prefix . $validated['catatan_laporan']) : $defaultNote,
            'status_audit' => 'baru',
        ]);

        $msg = $kategori === 'usul_regulasi'
            ? 'Usulan kebutuhan regulasi baru berhasil diteruskan ke Bagian Hukum & OPD terkait.'
            : 'Laporan dugaan pungli / klaim tidak sah berhasil dikirim ke Inspektorat Daerah.';

        return response()->json([
            'message' => $msg,
            'data' => $claimVerification,
        ]);
    }
}