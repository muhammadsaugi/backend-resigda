<?php

namespace App\Http\Controllers;

use App\Models\CivicAggregation;
use App\Models\Regulation;
use App\Models\RegulationRelation;
use App\Models\RevisionHistory;
use App\Models\RevisionTracking;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

class AdminController extends Controller
{
    public function __construct(protected AIService $aiService)
    {
    }

    /**
     * GET /api/admin/dashboard
     * Ringkasan statistik untuk semua role ASN (Staf OPD, Bagian Hukum, Inspektorat).
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'total_regulasi' => Regulation::count(),
            'regulasi_per_jenis' => Regulation::selectRaw('jenis, count(*) as total')
                ->groupBy('jenis')->pluck('total', 'jenis'),
            'konflik_terdeteksi' => RegulationRelation::where('jenis_relasi', 'konflik')
                ->where('status_tinjau', 'belum_ditinjau')->count(),
            'decay_score_tinggi' => Regulation::where('decay_score', '>=', 70)->count(),
            'closed_loop_berjalan' => RevisionTracking::whereNotIn('status', ['selesai'])->count(),
            'total_interaksi_bulan_ini' => DB::table('civic_interactions')
                ->whereMonth('interacted_at', now()->month)
                ->whereYear('interacted_at', now()->year)
                ->count(),
            // Dipakai dasbor Inspektorat (ringkasan cepat pengaduan warga, terlepas dari
            // metrik regulasi/hukum di atas yang bukan domain kewenangan mereka).
            'total_klaim_diverifikasi' => \App\Models\ClaimVerification::count(),
            'indikasi_pungli_dilaporkan' => \App\Models\ClaimVerification::where('dilaporkan_ke_inspektorat', true)
                ->where('kategori_laporan', 'pungli_petugas')->count(),
            'klaim_belum_ditindak' => \App\Models\ClaimVerification::where('dilaporkan_ke_inspektorat', true)
                ->where('status_audit', 'baru')->count(),
        ]);
    }

    /**
     * GET /api/admin/relations
     * Semua relasi graf untuk Conflict Graph Engine (visualisasi jaringan penuh).
     */
    public function listRelations(): JsonResponse
    {
        $relations = RegulationRelation::with([
            'source:id,judul,jenis,nomor,tahun',
            'target:id,judul,jenis,nomor,tahun',
        ])->orderByDesc('created_at')->get();

        return response()->json($relations);
    }

    // ==================== Manajemen Regulasi ====================

    /**
     * POST /api/admin/regulations
     * Role: bagian_hukum (dicek di route middleware, bukan di sini)
     */
    public function storeRegulation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jenis' => ['required', 'in:perda,perbup,se,instruksi_bupati'],
            'nomor' => ['required', 'string', 'max:50'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['required', 'string'],
            'opd' => ['nullable', 'string'],
            'tanggal_terbit' => ['nullable', 'date'],
            'status' => ['nullable', 'in:berlaku,dicabut,diubah'],
            'tags' => ['nullable', 'array'],
            'ringkasan' => ['nullable', 'string'],
            'articles' => ['nullable', 'array'],
            'articles.*.nomor_pasal' => ['required_with:articles', 'string'],
            'articles.*.isi' => ['required_with:articles', 'string'],
        ]);

        $articlesData = $validated['articles'] ?? null;
        unset($validated['articles']);

        $regulation = Regulation::create($validated);

        if (! empty($articlesData)) {
            foreach ($articlesData as $art) {
                $regulation->articles()->create([
                    'nomor_pasal' => $art['nomor_pasal'],
                    'isi' => $art['isi'],
                ]);
            }
        }

        return response()->json($regulation->load('articles'), 201);
    }

    /**
     * PUT /api/admin/regulations/{id}
     */
    public function updateRegulation(Request $request, Regulation $regulation): JsonResponse
    {
        $validated = $request->validate([
            'jenis' => ['sometimes', 'in:perda,perbup,se,instruksi_bupati'],
            'nomor' => ['sometimes', 'string', 'max:50'],
            'tahun' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['sometimes', 'string'],
            'opd' => ['nullable', 'string'],
            'tanggal_terbit' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:berlaku,dicabut,diubah'],
            'tags' => ['nullable', 'array'],
            'ringkasan' => ['nullable', 'string'],
            'articles' => ['nullable', 'array'],
            'articles.*.nomor_pasal' => ['required_with:articles', 'string'],
            'articles.*.isi' => ['required_with:articles', 'string'],
        ]);

        $articlesData = $validated['articles'] ?? null;
        unset($validated['articles']);

        $regulation->update($validated);

        if ($articlesData !== null) {
            $regulation->articles()->delete();
            foreach ($articlesData as $art) {
                $regulation->articles()->create([
                    'nomor_pasal' => $art['nomor_pasal'],
                    'isi' => $art['isi'],
                ]);
            }
        }

        return response()->json($regulation->load('articles'));
    }

    /**
     * DELETE /api/admin/regulations/{id}
     */
    public function destroyRegulation(Regulation $regulation): JsonResponse
    {
        $regulation->embeddings()->delete();
        $regulation->articles()->delete();
        $regulation->relationsAsSource()->delete();
        $regulation->relationsAsTarget()->delete();

        if ($regulation->file_path && Storage::disk('public')->exists($regulation->file_path)) {
            Storage::disk('public')->delete($regulation->file_path);
        }

        $regulation->delete();

        return response()->json(['message' => 'Regulasi berhasil dihapus']);
    }

    /**
     * POST /api/admin/regulations/{id}/embed
     * Trigger re-embedding manual — dipakai setelah admin edit ringkasan/isi
     * regulasi, supaya index vector pencarian ikut ter-update.
     */
    public function embedRegulation(Request $request, Regulation $regulation): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:10'],
            'article_id' => ['nullable', 'integer', 'exists:regulation_articles,id'],
        ]);

        try {
            $result = $this->aiService->embedContent(
                $regulation->id,
                $validated['content'],
                $validated['article_id'] ?? null
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }

    // ==================== Decay Tracker — Hitung Ulang Manual ====================

    /**
     * POST /api/admin/decay/recalculate
     * Role: bagian_hukum. Trigger manual untuk `regsida:calculate-decay`
     * (yang normalnya berjalan otomatis tiap malam lewat scheduler) —
     * dipakai staf/demo supaya tidak perlu menunggu sampai jadwal malam
     * untuk melihat efek data interaksi warga terbaru pada decay score.
     */
    public function recalculateDecay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);
        $threshold = $validated['threshold'] ?? 70;

        \Illuminate\Support\Facades\Artisan::call('regsida:calculate-decay', [
            '--threshold' => $threshold,
        ]);

        return response()->json([
            'message' => 'Decay score berhasil dihitung ulang.',
            'output' => trim(\Illuminate\Support\Facades\Artisan::output()),
        ]);
    }

    // ==================== Conflict Graph — Deteksi Otomatis ====================

    /**
     * POST /api/admin/relations/detect
     * Role: bagian_hukum. Menjalankan mesin deteksi relasi/konflik nyata:
     * 1) ai-service cari pasangan regulasi dengan embedding paling mirip (kandidat)
     * 2) tiap kandidat dinilai LLM: jenis relasi + confidence + alasan (dikutip dari isi pasal)
     * 3) hasil disimpan dengan status_tinjau=belum_ditinjau — TETAP butuh validasi
     *    manual staf lewat PATCH /relations/{id}, AI di sini hanya mengusulkan.
     * Kalau `regulation_id` diisi di body, hanya scan kandidat yang melibatkan
     * regulasi itu (dipakai tombol "Deteksi Ulang" di halaman detail regulasi).
     */
    public function detectRelations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'regulation_id' => ['nullable', 'integer', 'exists:regulations,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_distance' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $result = $this->aiService->detectRelations(
                $validated['regulation_id'] ?? null,
                $validated['limit'] ?? 15,
                $validated['max_distance'] ?? 0.35,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $disimpan = 0;
        $dilewati = 0;

        foreach ($result['relasi_ditemukan'] ?? [] as $found) {
            // Lewati kalau relasi jenis yang sama antara pasangan regulasi ini
            // sudah ada (searah atau berlawanan arah) dan belum ditolak —
            // supaya "deteksi ulang" tidak menumpuk duplikat tiap kali diklik.
            $sudahAda = RegulationRelation::where('jenis_relasi', $found['jenis_relasi'])
                ->where('status_tinjau', '!=', 'ditolak')
                ->where(function ($q) use ($found) {
                    $q->where(fn ($q2) => $q2->where('source_id', $found['source_id'])->where('target_id', $found['target_id']))
                        ->orWhere(fn ($q2) => $q2->where('source_id', $found['target_id'])->where('target_id', $found['source_id']));
                })
                ->exists();

            if ($sudahAda) {
                $dilewati++;
                continue;
            }

            RegulationRelation::create([
                'source_id' => $found['source_id'],
                'target_id' => $found['target_id'],
                'jenis_relasi' => $found['jenis_relasi'],
                'confidence' => $found['confidence'],
                'alasan' => $found['alasan'],
                'status_tinjau' => 'belum_ditinjau',
            ]);
            $disimpan++;
        }

        return response()->json([
            'kandidat_diperiksa' => $result['kandidat_diperiksa'] ?? 0,
            'relasi_baru_disimpan' => $disimpan,
            'relasi_dilewati_duplikat' => $dilewati,
        ]);
    }

    // ==================== Conflict Graph — Validasi Relasi ====================

    /**
     * PATCH /api/admin/relations/{id}
     * Role: bagian_hukum saja. Validasi atau tolak relasi hasil deteksi AI.
     */
    public function validateRelation(Request $request, RegulationRelation $relation): JsonResponse
    {
        $validated = $request->validate([
            'status_tinjau' => ['required', 'in:divalidasi,ditolak'],
        ]);

        $relation->update([
            'status_tinjau' => $validated['status_tinjau'],
            'ditinjau_oleh' => $request->user()->id,
        ]);

        return response()->json($relation->fresh());
    }

    // ==================== Closed-Loop Revisi ====================

    /**
     * PATCH /api/admin/revisions/{id}
     * Role: bagian_hukum. Update status closed-loop (5 tahap) + catat riwayatnya.
     * Catatan: endpoint ini tambahan dari saya, tidak ada di daftar endpoint
     * asli master prompt — tapi diperlukan supaya fitur "closed-loop status
     * tracking" di dashboard (fitur #8) benar-benar bisa diubah statusnya,
     * bukan cuma ditampilkan read-only.
     */
    public function updateRevisionStatus(Request $request, RevisionTracking $revision): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:terdeteksi,ditinjau,direkomendasikan,diproses_dprd,selesai'],
            'catatan' => ['nullable', 'string'],
        ]);

        $revision->update([
            'status' => $validated['status'],
            'catatan' => $validated['catatan'] ?? $revision->catatan,
        ]);

        RevisionHistory::create([
            'revision_tracking_id' => $revision->id,
            'status' => $validated['status'],
            'catatan' => $validated['catatan'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($revision->fresh('history'));
    }

    /**
     * POST /api/admin/regulations/{id}/upload-pdf
     * Role: bagian_hukum. Upload PDF regulasi asli -> ekstraksi teks ->
     * pecah jadi beberapa chunk (supaya aman di bawah batas input Gemini
     * Embedding) -> embed tiap chunk lewat AIService (reuse /embed FastAPI
     * yang sudah ada, tidak perlu endpoint baru di ai-service).
     */
    public function uploadPdf(Request $request, Regulation $regulation): JsonResponse
    {
        // PDF panjang -> banyak chunk -> banyak panggilan berurutan ke FastAPI
        // (masing-masing bisa kena antrian rate limiter) -> gampang lewat dari
        // batas default PHP max_execution_time (30 detik). Naikkan khusus di
        // request ini saja (bukan global php.ini) supaya endpoint lain tidak
        // ikut terpengaruh kalau ada yang nyangkut.
        set_time_limit(300); // 5 menit

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'], // maks 20MB
        ]);

        // Simpan file asli untuk arsip/referensi (kolom file_path sudah ada dari Fase 1)
        $path = $validated['file']->store("regulations/{$regulation->id}", 'local');
        $regulation->update(['file_path' => $path]);

        // Ekstraksi teks dari PDF
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile(Storage::disk('local')->path($path));
            $text = trim($pdf->getText());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal membaca isi PDF. Pastikan file tidak corrupt atau ter-password.',
                'error' => $e->getMessage(),
            ], 422);
        }

        if (strlen($text) < 20) {
            return response()->json([
                'message' => 'Teks yang berhasil diekstrak terlalu pendek/kosong. Kemungkinan PDF berupa hasil scan gambar (butuh OCR, belum didukung sistem ini).',
            ], 422);
        }

        // Pecah jadi chunk ~300 kata supaya aman di bawah batas input Gemini Embedding
        $chunks = $this->chunkText($text, wordsPerChunk: 300);

        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        foreach ($chunks as $chunk) {
            try {
                $this->aiService->embedContent($regulation->id, $chunk);
                $berhasil++;
            } catch (RuntimeException $e) {
                $gagal++;
                $errors[] = $e->getMessage();
            }
        }

        return response()->json([
            'regulation_id' => $regulation->id,
            'file_path' => $path,
            'total_karakter_diekstrak' => strlen($text),
            'total_chunk' => count($chunks),
            'chunk_berhasil_diembed' => $berhasil,
            'chunk_gagal' => $gagal,
            'errors' => $errors,
        ]);
    }

    /**
     * Pecah teks panjang jadi beberapa chunk berdasar jumlah kata.
     * Tidak ada overlap antar-chunk — cukup untuk kebutuhan sekarang,
     * bisa ditingkatkan nanti (mis. sliding window overlap) kalau hasil
     * similarity search kurang presisi di potongan kalimat.
     */
    private function chunkText(string $text, int $wordsPerChunk = 300): array
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];

        foreach (array_chunk($words, $wordsPerChunk) as $wordGroup) {
            $chunks[] = implode(' ', $wordGroup);
        }

        return $chunks;
    }

    // ==================== Manajemen Relasi Graf — Buat Baru ====================

    /**
     * POST /api/admin/relations
     * Role: bagian_hukum. Input manual relasi antar-regulasi (fitur #12
     * master prompt) — beda dengan PATCH /relations/{id} yang cuma
     * memvalidasi/menolak relasi yang SUDAH ada (biasanya hasil deteksi AI).
     * Karena ini input manual langsung oleh Bagian Hukum (bukan deteksi AI),
     * confidence otomatis 1.00 dan status_tinjau langsung "divalidasi".
     */
    public function storeRelation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:regulations,id', 'different:target_id'],
            'target_id' => ['required', 'integer', 'exists:regulations,id'],
            'jenis_relasi' => ['required', 'in:mencabut,dicabut_oleh,mengubah,diubah_oleh,merujuk,dirujuk_oleh,konflik'],
            'alasan' => ['nullable', 'string'],
        ]);

        $relation = RegulationRelation::create([
            ...$validated,
            'confidence' => 1.00,
            'status_tinjau' => 'divalidasi',
            'ditinjau_oleh' => $request->user()->id,
        ]);

        return response()->json($relation->fresh(['source', 'target']), 201);
    }

    // ==================== Export Laporan ====================

    /**
     * GET /api/admin/reports/export
     * Export PDF laporan agregasi Suara Warga bulan berjalan (atau ?bulan=2026-07).
     * Pakai barryvdh/laravel-dompdf — lihat FASE56_README.md untuk instalasi.
     */
    public function exportReport(Request $request)
    {
        // Validasi format YYYY-MM untuk mencegah path traversal di nama file download.
        $validated = $request->validate([
            'bulan' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $validated['bulan'] ?? now()->format('Y-m');

        $aggregations = CivicAggregation::whereRaw("to_char(periode, 'YYYY-MM') = ?", [$bulan])
            ->orderBy('topic')
            ->get();

        $pdf = app('dompdf.wrapper')->loadView('reports.civic-monthly', [
            'bulan' => $bulan,
            'aggregations' => $aggregations,
            'totalInteraksi' => $aggregations->sum('jumlah_interaksi'),
        ]);

        return $pdf->download("laporan-suara-warga-{$bulan}.pdf");
    }
}