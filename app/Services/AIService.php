<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Semua panggilan Laravel ke FastAPI (ai-service) lewat class ini,
 * supaya kalau nanti ada endpoint /classify baru (Fase 5) atau URL
 * ai-service berubah saat deploy, cukup ubah di satu tempat.
 */
class AIService
{
    protected string $baseUrl;
    protected string $internalToken;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ai_service.url'), '/');
        $this->internalToken = (string) config('services.ai_service.internal_token');
    }

    /**
     * Http::withHeaders(...) yang sudah dibekali token internal — ai-service
     * sekarang menolak semua request tanpa header ini (lihat main.py di
     * ai-service), supaya kalau portnya pernah kebuka langsung ke luar, orang
     * tidak bisa memanggilnya tanpa lewat Laravel sama sekali.
     */
    protected function http()
    {
        return Http::withHeaders(['X-Internal-Token' => $this->internalToken]);
    }

    /**
     * Panggil POST /rag di FastAPI.
     *
     * @throws RuntimeException kalau ai-service tidak bisa dihubungi atau error
     */
    public function askRag(string $query, int $topK = 5): array
    {
        try {
            $response = $this->http()->timeout(30)
                ->retry(2, 500) // coba lagi 2x kalau timeout/gagal koneksi sesaat
                ->post("{$this->baseUrl}/rag", [
                    'query' => $query,
                    'top_k' => $topK,
                ]);

            $response->throw(); // lempar exception kalau status code 4xx/5xx

            return $response->json();
        } catch (RequestException $e) {
            // ai-service merespons tapi dengan error (400/404/500/502 dari FastAPI)
            $detail = $e->response->json('detail') ?? $e->getMessage();
            throw new RuntimeException("AI service error: {$detail}", previous: $e);
        } catch (ConnectionException $e) {
            // ai-service tidak bisa dihubungi sama sekali (mati/salah URL)
            throw new RuntimeException(
                'Tidak bisa terhubung ke AI service. Pastikan FastAPI berjalan di ' . $this->baseUrl,
                previous: $e
            );
        }
    }

    /**
     * Panggil POST /embed di FastAPI. Dipakai admin saat tambah/edit regulasi (Fase 5/6).
     */
    public function embedContent(int $regulationId, string $content, ?int $articleId = null): array
    {
        try {
            // Timeout dinaikkan ke 60s (bukan 30s seperti askRag) karena panggilan
            // ini sering dilakukan berkali-kali berurutan (upload PDF banyak chunk),
            // dan tiap panggilan bisa tertahan antrian rate limiter Gemini di
            // ai-service sebelum benar-benar terkirim.
            $response = $this->http()->timeout(60)->post("{$this->baseUrl}/embed", [
                'regulation_id' => $regulationId,
                'content' => $content,
                'article_id' => $articleId,
            ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $detail = $e->response->json('detail') ?? $e->getMessage();
            throw new RuntimeException("Gagal embed konten: {$detail}", previous: $e);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Tidak bisa terhubung ke AI service untuk embedding.',
                previous: $e
            );
        }
    }

    /**
     * Panggil POST /detect-relations di FastAPI — mesin deteksi relasi/konflik
     * antar regulasi untuk Conflict Graph Engine. Timeout dinaikkan karena
     * tiap kandidat pasangan regulasi butuh satu panggilan LLM terpisah.
     */
    public function detectRelations(?int $regulationId = null, int $limit = 15, float $maxDistance = 0.35): array
    {
        try {
            $response = $this->http()->timeout(120)->post("{$this->baseUrl}/detect-relations", [
                'regulation_id' => $regulationId,
                'limit' => $limit,
                'max_distance' => $maxDistance,
            ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $detail = $e->response->json('detail') ?? $e->getMessage();
            throw new RuntimeException("Gagal deteksi relasi: {$detail}", previous: $e);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Tidak bisa terhubung ke AI service untuk deteksi relasi.',
                previous: $e
            );
        }
    }
}