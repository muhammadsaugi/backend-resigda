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
        $query = Regulation::query();

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

        return response()->json($regulations);
    }

    /**
     * GET /api/regulations/{regulation}
     * Sertakan pasal-pasal utama. Relasi graf (regulation_relations)
     * belum ada di fase ini — akan ditambahkan di Fase 6.
     */
    public function show(Regulation $regulation): JsonResponse
    {
        $regulation->load('articles');

        // Tambah counter "jumlah_dilihat" — dipakai Decay Tracker nanti (Fase 6)
        $regulation->increment('jumlah_dilihat');

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