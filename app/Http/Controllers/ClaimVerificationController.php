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
            $fullPath = Storage::disk('public')->path($path);

            // Re-encode lewat GD untuk SEMUA format yang diterima (dulu cuma JPEG).
            // Ini membuang EXIF/GPS demi anonimitas pelapor, SEKALIGUS menetralkan
            // payload apa pun yang disisipkan/di-append di belakang data gambar asli
            // (mis. byte tambahan setelah IEND pada PNG, atau trailer RIFF pada WEBP)
            // karena GD cuma membaca ulang piksel gambarnya, bukan byte mentah file.
            // Kalau file gagal didekode sebagai gambar asli meski lolos validasi mimes
            // (indikasi bukan gambar sungguhan/polyglot), tolak — jangan simpan mentah.
            if (! $this->reencodeImage($fullPath, $extension)) {
                Storage::disk('public')->delete($path);

                return response()->json([
                    'message' => 'File yang diunggah tidak dapat diproses sebagai gambar yang valid. Coba unggah ulang foto dalam format JPG, PNG, atau WEBP.',
                ], 422);
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
     *
     * KEAMANAN: {claimVerification} di-resolve dari ID berurutan biasa lewat route
     * model binding — tanpa pengecekan kepemilikan, siapa pun bisa menebak ID kecil
     * berurutan dan menandai/mengubah catatan laporan milik warga lain (IDOR).
     * Sekarang wajib membuktikan kepemilikan via session_id yang sama dipakai saat
     * klaim ini pertama kali dibuat (disimpan di localStorage browser pelapor —
     * pola yang sama seperti privacy-by-design session_id di seluruh aplikasi ini),
     * atau lewat citizen_id kalau klaim itu dibuat saat sudah login.
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
            'session_id' => ['required', 'uuid'],
            'catatan_laporan' => ['nullable', 'string', 'max:500'],
            'kategori_laporan' => ['nullable', 'in:pungli_petugas,usul_regulasi'],
        ]);

        $isOwner = $validated['session_id'] === $claimVerification->session_id
            || ($citizenUser instanceof \App\Models\Citizen && $citizenUser->id === $claimVerification->citizen_id);

        if (! $isOwner) {
            return response()->json([
                'message' => 'Klaim ini tidak ditemukan pada sesi Anda.',
            ], 403);
        }

        $kategori = $validated['kategori_laporan'] ?? 'pungli_petugas';
        $prefix = $hasFotoBukti ? '[DILENGKAPI FOTO BUKTI ANONIM] ' : '';
        $defaultNote = $kategori === 'usul_regulasi'
            ? $prefix . 'Usulan/aspirasi kebutuhan regulasi baru oleh warga karena belum diatur.'
            : $prefix . 'Pengaduan dugaan pungli / klaim layanan tidak sesuai regulasi oleh warga.';

        $claimVerification->update([
            'dilaporkan_ke_inspektorat' => true,
            'kategori_laporan' => $kategori,
            'catatan_laporan' => ! empty($validated['catatan_laporan']) ? ($prefix . $validated['catatan_laporan']) : $defaultNote,
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

    /**
     * Dekode file gambar lewat GD lalu tulis ulang di tempat yang sama sebagai
     * data piksel murni (bukan salinan byte mentah). Sengaja dipakai untuk semua
     * format yang diterima (jpg/jpeg/png/webp) — bukan cuma JPEG seperti
     * sebelumnya — supaya foto bukti anonim tidak pernah menyimpan byte apa pun
     * dari file asli yang tidak benar-benar bagian dari gambar (EXIF/GPS,
     * maupun data tersembunyi yang di-append di belakang gambar/"polyglot").
     * Return false kalau file gagal didekode sebagai gambar sungguhan.
     */
    private function reencodeImage(string $fullPath, string $extension): bool
    {
        $image = match ($extension) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($fullPath) : false,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($fullPath) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
            default => false,
        };

        if (! $image) {
            return false;
        }

        $success = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $fullPath, 90),
            'png' => imagepng($image, $fullPath, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $fullPath, 90) : false,
            default => false,
        };

        imagedestroy($image);

        return $success;
    }
}