<?php

namespace App\Http\Controllers;

use App\Models\CivicInteraction;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ChatController extends Controller
{
    public function __construct(protected AIService $aiService)
    {
    }

    /**
     * POST /api/chat
     *
     * Alur (lihat arsitektur di master prompt):
     * 1. Terima query + session_id dari React
     * 2. Forward ke FastAPI /rag
     * 3. Simpan hasil ke civic_interactions TANPA teks pertanyaan asli (privacy by design)
     * 4. Balikan jawaban lengkap ke React
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'session_id' => ['required', 'uuid'],
        ]);

        try {
            $result = $this->aiService->askRag($validated['query']);
        } catch (RuntimeException $e) {
            // ai-service mati/error — jangan simpan interaksi kosong, langsung kasih tahu frontend
            return response()->json([
                'message' => 'Gagal memproses pertanyaan. Silakan coba lagi sebentar lagi.',
                'error' => $e->getMessage(),
            ], 502);
        }

        // Ambil semua regulation_id unik dari sources untuk disimpan di civic_interactions
        $regulationIds = collect($result['sources'] ?? [])
            ->pluck('regulation_id')
            ->unique()
            ->values()
            ->all();

        // PRIVACY BY DESIGN: hanya simpan session_id, topic, sentiment, regulation_ids,
        // confidence, dan timestamp. TIDAK PERNAH simpan $validated['query'] di sini.
        CivicInteraction::create([
            'session_id' => $validated['session_id'],
            'citizen_id' => $request->user('sanctum') instanceof \App\Models\Citizen ? $request->user('sanctum')->id : null,
            'topic' => $result['topic'] ?? null,       // masih null sampai Fase 5 (/classify)
            'sentiment' => $result['sentiment'] ?? null, // masih null sampai Fase 5
            'regulation_ids' => $regulationIds,
            'confidence_score' => $result['confidence'] ?? null,
            'interacted_at' => now(),
        ]);

        // Response ke frontend — boleh berisi teks jawaban & sumber lengkap,
        // karena ini tidak disimpan di DB, hanya dikirim balik ke browser pengguna.
        return response()->json([
            'answer' => $result['answer'],
            'sources' => $result['sources'],
            'confidence' => $result['confidence'],
        ]);
    }
}