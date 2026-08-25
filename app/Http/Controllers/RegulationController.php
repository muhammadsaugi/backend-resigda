<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegulationController extends Controller
{
    /**
     * GET /api/regulations
     * Query params opsional:
     *   ?search=kata kunci   → cari di judul & ringkasan
     *   ?jenis=perda          → filter jenis (perda/perbup/se/instruksi_bupati)
     *   ?status=berlaku       → filter status
     *   ?per_page=10          → jumlah per halaman (default 10)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Regulation::withCount('embeddings');

        if ($request->filled('search')) {
            $keyword = $request->string('search');
            $query->where(function ($q) use ($keyword) {
                $q->where('judul', 'ilike', "%{$keyword}%")
                    ->orWhere('ringkasan', 'ilike', "%{$keyword}%");
            });
        }

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $perPage = (int) $request->input('per_page', 10);
        $regulations = $query->orderByDesc('tanggal_terbit')->paginate($perPage);

        $regulations->getCollection()->transform(function ($reg) {
            $reg->has_pdf = !empty($reg->file_path);
            $reg->pdf_url = $reg->file_path ? asset('storage/' . ltrim($reg->file_path, '/')) : null;
            return $reg;
        });

        return response()->json($regulations);
    }

    /**
     * GET /api/regulations/{regulation}
     * Sertakan pasal-pasal utama, ringkasan fallback, dan info PDF.
     */
    public function show(Regulation $regulation): JsonResponse
    {
        $regulation->load(['articles', 'embeddings:id,regulation_id,content_chunk']);

        // Incremental views count for Decay Tracker
        $regulation->increment('jumlah_dilihat');

        // Fallback hierarchy for content_summary:
        // 1. $regulation->ringkasan (if present)
        // 2. First chunk of regulation_embeddings (if ringkasan empty)
        // 3. Articles snippet (if articles present)
        $firstChunk = $regulation->embeddings->first()?->content_chunk;
        $contentSummary = $regulation->ringkasan;

        if (empty($contentSummary) && !empty($firstChunk)) {
            $contentSummary = $firstChunk;
        }

        $regulation->content_summary = $contentSummary;
        $regulation->has_pdf = !empty($regulation->file_path);
        $regulation->pdf_url = $regulation->file_path ? asset('storage/' . ltrim($regulation->file_path, '/')) : null;
        $regulation->embedding_count = $regulation->embeddings->count();

        return response()->json($regulation);
    }

    /**
     * GET /api/regulations/{regulation}/relations
     * Data mentah untuk Conflict Graph Engine (visualisasi SVG di frontend).
     * Digabung dua arah (sebagai source maupun target) supaya frontend
     * tidak perlu tahu arah relasi untuk menggambar node & edge graf.
     */
    public function relations(Regulation $regulation): JsonResponse
    {
        $asSource = $regulation->relationsAsSource()->with('target:id,judul,jenis,nomor,tahun')->get();
        $asTarget = $regulation->relationsAsTarget()->with('source:id,judul,jenis,nomor,tahun')->get();

        return response()->json([
            'regulation_id' => $regulation->id,
            'relations_as_source' => $asSource,
            'relations_as_target' => $asTarget,
        ]);
    }
}