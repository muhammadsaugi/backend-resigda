<?php

namespace Database\Seeders;

use App\Models\Regulation;
use App\Models\RegulationArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class RegulationRealSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = storage_path('regulasi_data.json');
        if (! File::exists($jsonPath)) {
            return;
        }

        $items = json_decode(File::get($jsonPath), true);

        // Ensure public storage directory exists
        Storage::disk('public')->makeDirectory('regulasi');

        foreach ($items as $item) {
            // Copy PDF file to public storage
            $srcFile = base_path('../' . $item['src_file']);
            $destRelPath = 'regulasi/' . $item['filename'];
            if (File::exists($srcFile)) {
                Storage::disk('public')->put($destRelPath, File::get($srcFile));
            }

            // Create Regulation
            $regulation = Regulation::create([
                'jenis' => $item['jenis'],
                'nomor' => (string) $item['nomor'],
                'tahun' => (int) $item['tahun'],
                'judul' => $item['judul'],
                'opd' => $item['opd'],
                'tanggal_terbit' => $item['tanggal_terbit'],
                'status' => $item['status'],
                'tags' => $item['tags'],
                'ringkasan' => $item['ringkasan'],
                'file_path' => $destRelPath,
            ]);

            // Create RegulationArticle entries
            if (! empty($item['articles'])) {
                foreach ($item['articles'] as $art) {
                    RegulationArticle::create([
                        'regulation_id' => $regulation->id,
                        'nomor_pasal' => $art['nomor_pasal'],
                        'isi' => $art['isi'],
                    ]);
                }
            }
        }
    }
}
