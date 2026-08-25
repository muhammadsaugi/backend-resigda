<?php

namespace App\Console\Commands;

use App\Models\Regulation;
use App\Services\AIService;
use Illuminate\Console\Command;

/**
 * Dipakai setelah migrate:fresh/seed supaya tidak perlu curl /embed satu-satu
 * manual untuk tiap regulasi. Embed pakai kolom `ringkasan` (bukan isi pasal
 * lengkap, karena data seed dummy belum punya regulation_articles terisi).
 */
class EmbedAllRegulations extends Command
{
    protected $signature = 'regsida:embed-all {--force : Embed ulang walau sudah pernah di-embed}';

    protected $description = 'Embed semua regulasi yang belum punya embedding ke ai-service';

    public function handle(AIService $aiService): int
    {
        $query = Regulation::with('articles');

        if (! $this->option('force')) {
            $query->doesntHave('embeddings');
        }

        $regulations = $query->get();

        if ($regulations->isEmpty()) {
            $this->info('Tidak ada regulasi yang perlu di-embed (semua sudah punya embedding, pakai --force untuk embed ulang).');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($regulations->count());
        $bar->start();

        $berhasil = 0;
        $gagal = 0;

        foreach ($regulations as $regulation) {
            try {
                // 1. Embed Ringkasan Regulasi
                $summaryText = "[{$regulation->judul}]\n{$regulation->ringkasan}";
                $aiService->embedContent($regulation->id, $summaryText);

                // 2. Embed Tiap Pasal / Ketentuan
                foreach ($regulation->articles as $article) {
                    $articleText = "[{$regulation->judul} - {$article->nomor_pasal}]\n{$article->isi}";
                    $aiService->embedContent($regulation->id, $articleText, $article->id);
                }

                $berhasil++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Gagal embed regulasi #{$regulation->id} ({$regulation->judul}): {$e->getMessage()}");
                $gagal++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai. Berhasil: {$berhasil}, Gagal: {$gagal}.");

        return self::SUCCESS;
    }
}