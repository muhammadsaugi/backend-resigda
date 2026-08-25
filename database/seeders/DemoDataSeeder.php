<?php

namespace Database\Seeders;

use App\Models\CivicInteraction;
use App\Models\ClaimVerification;
use App\Models\Regulation;
use App\Models\RegulationRelation;
use App\Models\RevisionHistory;
use App\Models\RevisionTracking;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data pendukung demo untuk halaman admin (Fase A roadmap frontend).
 * Isi: relasi graf, closed-loop revisi, interaksi warga, verifikasi klaim.
 * Aman dijalankan ulang — skip jika data demo sudah ada.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (RegulationRelation::exists()) {
            $this->command?->info('DemoDataSeeder: relasi sudah ada, lewati seed relasi.');
        } else {
            $this->seedRelations();
        }

        if (RevisionTracking::exists()) {
            $this->command?->info('DemoDataSeeder: revision_tracking sudah ada, lewati.');
        } else {
            $this->seedRevisions();
        }

        if (CivicInteraction::count() >= 10) {
            $this->command?->info('DemoDataSeeder: civic_interactions sudah cukup, lewati.');
        } else {
            $this->seedCivicInteractions();
        }

        if (ClaimVerification::exists()) {
            $this->command?->info('DemoDataSeeder: claim_verifications sudah ada, lewati.');
        } else {
            $this->seedClaimVerifications();
        }

        $this->seedDecayScores();
    }

    private function reg(string $jenis, string $nomor, int $tahun): Regulation
    {
        return Regulation::where('jenis', $jenis)
            ->where('nomor', $nomor)
            ->where('tahun', $tahun)
            ->first() ?? Regulation::where('jenis', $jenis)->first() ?? Regulation::first();
    }

    private function seedDecayScores(): void
    {
        $regulations = Regulation::take(8)->get();
        $scores = [45.00, 78.50, 88.00, 82.00, 61.00, 35.00, 28.00, 42.00];

        foreach ($regulations as $index => $reg) {
            $reg->update(['decay_score' => $scores[$index % count($scores)]]);
        }
    }

    private function seedRelations(): void
    {
        $bagianHukum = User::where('role', 'bagian_hukum')->first();

        $perdaSampah = $this->reg('perda', '2', 2020);
        $perbupBankSampah = $this->reg('perbup', '44', 2023);
        $perdaPerizinan = $this->reg('perda', '7', 2021);
        $perbupImb = $this->reg('perbup', '35', 2023);
        $seRamadan = $this->reg('se', '060/875', 2023);
        $instruksiProtokol = $this->reg('instruksi_bupati', '5', 2022);
        $perdaRtrw = $this->reg('perda', '9', 2019);

        $relations = [
            [
                'source_id' => $perbupBankSampah->id,
                'target_id' => $perdaSampah->id,
                'jenis_relasi' => 'merujuk',
                'confidence' => 0.95,
                'alasan' => 'Perbup Bank Sampah merupakan pedoman teknis turunan Perda Pengelolaan Sampah.',
                'status_tinjau' => 'divalidasi',
            ],
            [
                'source_id' => $perbupImb->id,
                'target_id' => $perdaPerizinan->id,
                'jenis_relasi' => 'merujuk',
                'confidence' => 0.88,
                'alasan' => 'Tata cara IMB/PBG merujuk kerangka perizinan berbasis risiko di Perda terkait.',
                'status_tinjau' => 'divalidasi',
            ],
            [
                'source_id' => $perbupImb->id,
                'target_id' => $perdaPerizinan->id,
                'jenis_relasi' => 'konflik',
                'confidence' => 0.62,
                'alasan' => 'Potensi tumpang tindih persyaratan teknis PBG antara Perbup IMB dan Perda Perizinan — perlu harmonisasi pasal verifikasi.',
                'status_tinjau' => 'belum_ditinjau',
            ],
            [
                'source_id' => $perdaRtrw->id,
                'target_id' => $perbupImb->id,
                'jenis_relasi' => 'konflik',
                'confidence' => 0.54,
                'alasan' => 'Ketentuan zonasi RDTR berpotensi bertentangan dengan mekanisme persetujuan bangunan pada zona tertentu.',
                'status_tinjau' => 'belum_ditinjau',
            ],
            [
                'source_id' => $seRamadan->id,
                'target_id' => $perbupImb->id,
                'jenis_relasi' => 'merujuk',
                'confidence' => 0.40,
                'alasan' => 'Relasi lemah terdeteksi otomatis — memerlukan verifikasi manual (confidence rendah).',
                'status_tinjau' => 'belum_ditinjau',
            ],
            [
                'source_id' => $instruksiProtokol->id,
                'target_id' => $perbupImb->id,
                'jenis_relasi' => 'mengubah',
                'confidence' => 0.71,
                'alasan' => 'Instruksi protokol kesehatan pernah mengubah prosedur layanan tatap muka perizinan — konteks sudah tidak relevan pasca pencabutan.',
                'status_tinjau' => 'divalidasi',
            ],
        ];

        foreach ($relations as $data) {
            RegulationRelation::create([
                ...$data,
                'ditinjau_oleh' => $data['status_tinjau'] === 'divalidasi' ? $bagianHukum?->id : null,
            ]);
        }
    }

    private function seedRevisions(): void
    {
        $bagianHukum = User::where('role', 'bagian_hukum')->first();

        $items = [
            [
                'regulation' => $this->reg('se', '060/875', 2023),
                'status' => 'direkomendasikan',
                'catatan' => 'Decay Score 88 — konteks Ramadan sudah berlalu. Direkomendasikan pencabutan formal.',
                'history' => [
                    ['status' => 'terdeteksi', 'catatan' => 'Terdeteksi otomatis — usia regulasi terbatas, frekuensi pertanyaan sangat rendah.', 'days_ago' => 90],
                    ['status' => 'ditinjau', 'catatan' => 'Ditinjau staf hukum — dikonfirmasi sudah tidak berlaku.', 'days_ago' => 60],
                    ['status' => 'direkomendasikan', 'catatan' => 'Direkomendasikan pencabutan kepada Kepala Bagian Hukum.', 'days_ago' => 30],
                ],
            ],
            [
                'regulation' => $this->reg('perbup', '8', 2020),
                'status' => 'ditinjau',
                'catatan' => 'Tarif retribusi puskesmas belum disesuaikan standar terbaru. Sedang dikoordinasikan Dinas Kesehatan.',
                'history' => [
                    ['status' => 'terdeteksi', 'catatan' => 'Decay Score 78 — usia 6 tahun, status diubah sebagian.', 'days_ago' => 45],
                    ['status' => 'ditinjau', 'catatan' => 'Dikoordinasikan dengan Dinas Kesehatan untuk verifikasi relevansi tarif.', 'days_ago' => 15],
                ],
            ],
            [
                'regulation' => $this->reg('perda', '9', 2019),
                'status' => 'diproses_dprd',
                'catatan' => 'Potensi konflik zonasi dengan mekanisme PBG sudah dikonfirmasi. Draf revisi diajukan ke Bapemperda.',
                'history' => [
                    ['status' => 'terdeteksi', 'catatan' => 'Conflict Graph mendeteksi potensi konflik dengan Perbup IMB (confidence 54%).', 'days_ago' => 120],
                    ['status' => 'ditinjau', 'catatan' => 'Staf hukum mengonfirmasi relasi konflik valid.', 'days_ago' => 90],
                    ['status' => 'direkomendasikan', 'catatan' => 'Direkomendasikan penyesuaian klausul zonasi.', 'days_ago' => 60],
                    ['status' => 'diproses_dprd', 'catatan' => 'Draf revisi resmi diajukan ke Bapemperda DPRD.', 'days_ago' => 20],
                ],
            ],
            [
                'regulation' => $this->reg('instruksi_bupati', '5', 2022),
                'status' => 'terdeteksi',
                'catatan' => 'Decay Score 82 — konteks pandemi sudah tidak relevan. Belum ditugaskan ke staf peninjau.',
                'history' => [
                    ['status' => 'terdeteksi', 'catatan' => 'Terdeteksi otomatis — kombinasi decay tinggi dan status dicabut.', 'days_ago' => 7],
                ],
            ],
            [
                'regulation' => $this->reg('perda', '2', 2020),
                'status' => 'selesai',
                'catatan' => 'Telah diperbarui melalui Perbup Bank Sampah 2023 sebagai turunan yang lebih spesifik.',
                'history' => [
                    ['status' => 'terdeteksi', 'catatan' => 'Decay Score moderat — frekuensi pertanyaan rendah.', 'days_ago' => 200],
                    ['status' => 'ditinjau', 'catatan' => 'Dikoordinasikan dengan Dinas Lingkungan Hidup.', 'days_ago' => 170],
                    ['status' => 'direkomendasikan', 'catatan' => 'Disepakati pembaruan melalui peraturan turunan.', 'days_ago' => 140],
                    ['status' => 'selesai', 'catatan' => 'Perbup Bank Sampah 2023 diterbitkan sebagai turunan.', 'days_ago' => 100],
                ],
            ],
        ];

        foreach ($items as $item) {
            $tracking = RevisionTracking::create([
                'regulation_id' => $item['regulation']->id,
                'status' => $item['status'],
                'catatan' => $item['catatan'],
                'ditugaskan_ke' => $bagianHukum?->id,
            ]);

            foreach ($item['history'] as $h) {
                RevisionHistory::create([
                    'revision_tracking_id' => $tracking->id,
                    'status' => $h['status'],
                    'catatan' => $h['catatan'],
                    'created_by' => $bagianHukum?->id,
                    'created_at' => now()->subDays($h['days_ago']),
                    'updated_at' => now()->subDays($h['days_ago']),
                ]);
            }
        }
    }

    private function seedCivicInteractions(): void
    {
        $samples = [
            ['topic' => 'perizinan', 'sentiment' => 'bingung', 'kecamatan' => 'Krian', 'regulation_ids' => [6, 2], 'confidence' => 0.82],
            ['topic' => 'perizinan', 'sentiment' => 'indikasi_pungli', 'kecamatan' => 'Krian', 'regulation_ids' => [6], 'confidence' => 0.75],
            ['topic' => 'perizinan', 'sentiment' => 'keberatan', 'kecamatan' => 'Krian', 'regulation_ids' => [6, 2], 'confidence' => 0.68],
            ['topic' => 'retribusi_pajak', 'sentiment' => 'informasi', 'kecamatan' => 'Waru', 'regulation_ids' => [1], 'confidence' => 0.91],
            ['topic' => 'retribusi_pajak', 'sentiment' => 'indikasi_pungli', 'kecamatan' => 'Waru', 'regulation_ids' => [1], 'confidence' => 0.70],
            ['topic' => 'lingkungan_sampah', 'sentiment' => 'informasi', 'kecamatan' => 'Taman', 'regulation_ids' => [3, 10], 'confidence' => 0.88],
            ['topic' => 'pungli_pelayanan', 'sentiment' => 'indikasi_pungli', 'kecamatan' => 'Krian', 'regulation_ids' => [16], 'confidence' => 0.65],
            ['topic' => 'pungli_pelayanan', 'sentiment' => 'indikasi_pungli', 'kecamatan' => 'Krian', 'regulation_ids' => [16, 6], 'confidence' => 0.72],
            ['topic' => 'tata_ruang', 'sentiment' => 'bingung', 'kecamatan' => 'Buduran', 'regulation_ids' => [4, 6], 'confidence' => 0.79],
            ['topic' => 'pendidikan', 'sentiment' => 'informasi', 'kecamatan' => 'Sidoarjo Kota', 'regulation_ids' => [5], 'confidence' => 0.93],
            ['topic' => 'kesehatan', 'sentiment' => 'puas', 'kecamatan' => 'Sidoarjo Kota', 'regulation_ids' => [11], 'confidence' => 0.85],
            ['topic' => 'perizinan', 'sentiment' => 'indikasi_pungli', 'kecamatan' => 'Krian', 'regulation_ids' => [6], 'confidence' => 0.60],
            ['topic' => 'bantuan_sosial', 'sentiment' => 'informasi', 'kecamatan' => 'Taman', 'regulation_ids' => [8], 'confidence' => 0.87],
            ['topic' => 'infrastruktur', 'sentiment' => 'keberatan', 'kecamatan' => 'Waru', 'regulation_ids' => [4], 'confidence' => 0.74],
            ['topic' => 'perizinan', 'sentiment' => 'bingung', 'kecamatan' => 'Krian', 'regulation_ids' => [2, 6], 'confidence' => 0.80],
        ];

        foreach ($samples as $i => $s) {
            CivicInteraction::create([
                'session_id' => (string) Str::uuid(),
                'topic' => $s['topic'],
                'sentiment' => $s['sentiment'],
                'regulation_ids' => $s['regulation_ids'],
                'confidence_score' => $s['confidence'],
                'kecamatan' => $s['kecamatan'],
                'interacted_at' => now()->subDays($i % 14)->subHours($i),
            ]);
        }
    }

    private function seedClaimVerifications(): void
    {
        $claims = [
            ['klaim' => 'Petugas meminta biaya tambahan Rp150.000 untuk percepatan proses', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Krian', 'layanan' => 'Perpanjangan Izin Usaha Mikro', 'days_ago' => 45],
            ['klaim' => 'Diminta membayar Rp100.000 di luar tarif resmi untuk proses PBG', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Krian', 'layanan' => 'Pengesahan PBG Rumah Tinggal', 'days_ago' => 38],
            ['klaim' => 'Biaya survei lokasi yang tidak tercantum di brosur retribusi', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Krian', 'layanan' => 'Pengesahan PBG Rumah Tinggal', 'days_ago' => 32],
            ['klaim' => 'Petugas loket meminta uang jasa percepatan Rp75.000', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Krian', 'layanan' => 'Perpanjangan Izin Usaha Mikro', 'days_ago' => 27],
            ['klaim' => 'Diminta membayar denda tambahan tanpa surat ketetapan resmi', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Waru', 'layanan' => 'Pajak Restoran', 'days_ago' => 60],
            ['klaim' => 'Verifikasi nilai objek pajak ditagih biaya konsultasi tambahan', 'hasil' => 'sebagian_sesuai', 'kecamatan' => 'Taman', 'layanan' => 'Pengurusan PBB', 'days_ago' => 85],
            ['klaim' => 'Klarifikasi tarif Pajak Hotel sesuai Perda', 'hasil' => 'ditemukan', 'kecamatan' => 'Sidoarjo Kota', 'layanan' => 'Pajak Hotel', 'days_ago' => 75],
            ['klaim' => 'Diminta menyertakan biaya administrasi tambahan tunai', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Krian', 'layanan' => 'Perpanjangan Izin Usaha Mikro', 'days_ago' => 20],
            ['klaim' => 'Klarifikasi denda keterlambatan pemasangan reklame', 'hasil' => 'ditemukan', 'kecamatan' => 'Buduran', 'layanan' => 'Pajak Reklame', 'days_ago' => 120],
            ['klaim' => 'Diminta jasa pengurusan cepat senilai Rp200.000', 'hasil' => 'tidak_ditemukan', 'kecamatan' => 'Krian', 'layanan' => 'Pengesahan PBG Rumah Tinggal', 'days_ago' => 14],
        ];

        foreach ($claims as $c) {
            ClaimVerification::create([
                'session_id' => (string) Str::uuid(),
                'klaim_text' => $c['klaim'],
                'hasil_verifikasi' => $c['hasil'],
                'regulation_ids' => [6],
                'kecamatan' => $c['kecamatan'],
                'layanan' => $c['layanan'],
                'created_at' => now()->subDays($c['days_ago']),
                'updated_at' => now()->subDays($c['days_ago']),
            ]);
        }
    }
}
