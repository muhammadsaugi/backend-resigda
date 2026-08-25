<?php

namespace Database\Seeders;

use App\Models\Citizen;
use App\Models\CivicInteraction;
use App\Models\ClaimVerification;
use App\Models\Regulation;
use App\Models\RevisionTracking;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoStateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. SEED / UPDATE DEMO USERS (4 AKUN DEMO)
        $password = Hash::make('Demo@12345');

        // Warga Demo
        Citizen::updateOrCreate(
            ['email' => 'warga@demo.id'],
            [
                'name' => 'Warga Sidoarjo Demo',
                'phone_number' => '081234567890',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        // ASN Bagian Hukum
        $hukumUser = User::updateOrCreate(
            ['nip' => '198703152011012002'],
            [
                'email' => 'bagian.hukum@sidoarjokab.go.id',
                'name' => 'Siti Rahma (Bagian Hukum)',
                'role' => 'bagian_hukum',
                'opd' => 'Bagian Hukum Setda',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // ASN Inspektorat
        User::updateOrCreate(
            ['nip' => '198209202009011003'],
            [
                'email' => 'inspektorat@sidoarjokab.go.id',
                'name' => 'Budi Santoso (Inspektorat)',
                'role' => 'inspektorat',
                'opd' => 'Inspektorat Daerah',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // ASN Staf OPD (DPMPTSP)
        User::updateOrCreate(
            ['nip' => '198501012010011001'],
            [
                'email' => 'staf.opd@dpmptsp.sidoarjo.go.id',
                'name' => 'Ahmad Fauzi (DPMPTSP)',
                'role' => 'staf_opd',
                'opd' => 'DPMPTSP',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. SEED TABEL civic_interactions (80 Entri Simulasi)
        CivicInteraction::truncate();

        $topics = array_merge(
            array_fill(0, 28, 'pajak_retribusi'),
            array_fill(0, 20, 'perizinan_umkm'),
            array_fill(0, 12, 'pungli_indikasi'),
            array_fill(0, 8, 'tata_ruang'),
            array_fill(0, 6, 'kepegawaian'),
            array_fill(0, 6, 'lainnya')
        );

        $sentiments = array_merge(
            array_fill(0, 32, 'bingung_prosedur'),
            array_fill(0, 24, 'keberatan_tarif'),
            array_fill(0, 12, 'indikasi_pungli'),
            array_fill(0, 12, 'informasi_umum')
        );

        shuffle($topics);
        shuffle($sentiments);

        $kecamatans = ['Sidoarjo', 'Waru', 'Candi', 'Krian', 'Taman', 'Porong', 'Gedangan', 'Sukodono', 'Tulangan', 'Krembung', 'Wonoayu', 'Prambon', 'Tarik', 'Sedati', 'Jabon', 'Buduran', 'Balongbendo'];
        $months = ['2026-05', '2026-06', '2026-07', '2026-08'];

        $regulations = Regulation::pluck('id')->all();
        if (empty($regulations)) {
            $regulations = [1, 2, 3, 4, 5];
        }

        for ($i = 0; $i < 80; $i++) {
            $month = $months[$i % 4];
            $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $hour = str_pad(rand(8, 20), 2, '0', STR_PAD_LEFT);
            $minute = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT);
            $timestamp = "{$month}-{$day} {$hour}:{$minute}:00";

            $regCount = rand(1, 3);
            $randRegs = (array) array_rand(array_flip($regulations), min($regCount, count($regulations)));

            CivicInteraction::create([
                'session_id' => (string) Str::uuid(),
                'citizen_id' => null,
                'topic' => $topics[$i],
                'sentiment' => $sentiments[$i],
                'regulation_ids' => array_values($randRegs),
                'confidence_score' => round(rand(70, 98) / 100, 2),
                'kecamatan' => $kecamatans[array_rand($kecamatans)],
                'interacted_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // 3. SEED TABEL claim_verifications (15 Entri)
        ClaimVerification::truncate();

        // 7 Entri: tidak_ditemukan & dilaporkan_ke_inspektorat = true (Krian:4, Waru:2, Taman:1)
        $inspektoratClaims = [
            ['kecamatan' => 'Krian', 'layanan' => 'Perizinan Usaha Mikro', 'klaim_text' => 'Petugas meminta biaya sertifikat tambahan Rp150.000 tanpa kuitansi resmi.'],
            ['kecamatan' => 'Krian', 'layanan' => 'Retribusi Pelayanan Kesehatan', 'klaim_text' => 'Dikenakan biaya tambahan obat di luar ketentuan Perbup.'],
            ['kecamatan' => 'Krian', 'layanan' => 'Pengesahan PBG Rumah', 'klaim_text' => 'Dipungut biaya survei lapangan Rp500.000 tanpa tanda terima.'],
            ['kecamatan' => 'Krian', 'layanan' => 'Izin Reklame Usaha', 'klaim_text' => 'Petugas meminta pembayaran tunai di tempat untuk izin papan nama.'],
            ['kecamatan' => 'Waru', 'layanan' => 'Perizinan Usaha Mikro', 'klaim_text' => 'Petugas kecamatan minta biaya administrasi percepatan izin Rp200.000.'],
            ['kecamatan' => 'Waru', 'layanan' => 'Pengelolaan Sampah Pasar', 'klaim_text' => 'Pungutan kebersihan pasar melebihi tarif resmi Perda.'],
            ['kecamatan' => 'Taman', 'layanan' => 'Persetujuan Bangunan Gedung', 'klaim_text' => 'Pungli izin bangunan ruko sebesar Rp1.000.000 oleh oknum.'],
        ];

        foreach ($inspektoratClaims as $item) {
            ClaimVerification::create([
                'session_id' => (string) Str::uuid(),
                'citizen_id' => null,
                'klaim_text' => $item['klaim_text'],
                'hasil_verifikasi' => 'tidak_ditemukan',
                'regulation_ids' => [],
                'kecamatan' => $item['kecamatan'],
                'layanan' => $item['layanan'],
                'kategori_laporan' => 'pungli_petugas',
                'dilaporkan_ke_inspektorat' => true,
                'catatan_laporan' => 'Pelaporan indikasi pungli/klaim tak sah diteruskan ke Inspektorat Daerah.',
                'status_audit' => 'baru',
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // 5 Entri: tidak_ditemukan & dilaporkan_ke_inspektorat = false
        $nonReportedClaims = [
            ['kecamatan' => 'Sidoarjo', 'layanan' => 'Izin Operasional Restoran', 'klaim_text' => 'Belum ada aturan mengenai batas jam musik keras malam hari.'],
            ['kecamatan' => 'Gedangan', 'layanan' => 'Pengelolaan Bank Sampah', 'klaim_text' => 'Aturan pemilahan sampah organik belum diatur di pemukiman.'],
            ['kecamatan' => 'Candi', 'layanan' => 'Parkir Berlangganan', 'klaim_text' => 'Jukir liar memungut biaya parkir padahal ada stiker berlangganan.'],
            ['kecamatan' => 'Sukodono', 'layanan' => 'Bantuan Sosial Usaha', 'klaim_text' => 'Kriteria penerima bansos UMKM kurang transparan di tingkat desa.'],
            ['kecamatan' => 'Porong', 'layanan' => 'Izin Usaha Peternakan', 'klaim_text' => 'Ketentuan jarak kandang dengan pemukiman belum terakomodasi.'],
        ];

        foreach ($nonReportedClaims as $item) {
            ClaimVerification::create([
                'session_id' => (string) Str::uuid(),
                'citizen_id' => null,
                'klaim_text' => $item['klaim_text'],
                'hasil_verifikasi' => 'tidak_ditemukan',
                'regulation_ids' => [],
                'kecamatan' => $item['kecamatan'],
                'layanan' => $item['layanan'],
                'kategori_laporan' => 'usul_regulasi',
                'dilaporkan_ke_inspektorat' => false,
                'catatan_laporan' => null,
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // 3 Entri: ditemukan
        $foundClaims = [
            ['kecamatan' => 'Sidoarjo', 'layanan' => 'Retribusi Kesehatan Puskesmas', 'klaim_text' => 'Tarif pemeriksaan puskesmas Rp15.000 sudah sesuai Perda Retribusi.'],
            ['kecamatan' => 'Waru', 'layanan' => 'PBG Rumah Tinggal', 'klaim_text' => 'Prosedur IMB/PBG rumah tinggal telah sesuai Perbup Tata Ruang.'],
            ['kecamatan' => 'Krian', 'layanan' => 'Izin Usaha Mikro', 'klaim_text' => 'Penerbitan NIB gratis tanpa biaya sesuai kebijakan daerah.'],
        ];

        foreach ($foundClaims as $item) {
            ClaimVerification::create([
                'session_id' => (string) Str::uuid(),
                'citizen_id' => null,
                'klaim_text' => $item['klaim_text'],
                'hasil_verifikasi' => 'ditemukan',
                'regulation_ids' => array_slice($regulations, 0, 2),
                'kecamatan' => $item['kecamatan'],
                'layanan' => $item['layanan'],
                'kategori_laporan' => 'usul_regulasi',
                'dilaporkan_ke_inspektorat' => false,
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // 4. SEED TABEL revision_tracking (5 Entri dengan 5 Status)
        RevisionTracking::truncate();

        $allRegs = Regulation::take(5)->get();

        $revisionSpecs = [
            [
                'status' => 'terdeteksi',
                'catatan' => 'Regulasi protokol kesehatan 2021 perlu penyesuaian paska pandemi COVID-19.',
            ],
            [
                'status' => 'ditinjau',
                'catatan' => 'Regulasi retribusi TKA 2014 perlu harmonisasi dengan PP Tenaga Kerja Asing terbaru.',
            ],
            [
                'status' => 'direkomendasikan',
                'catatan' => 'Regulasi pajak restoran 2018 direkomendasikan revisi penyesuaian tarif Pajak Barang dan Jasa Tertentu (PBJT).',
            ],
            [
                'status' => 'diproses_dprd',
                'catatan' => 'Regulasi RDTR Balongbendo 2019 sedang dalam rancangan pembahasan Perda bersama DPRD Sidoarjo.',
            ],
            [
                'status' => 'selesai',
                'catatan' => 'Instruksi pemulihan ekonomi 2022 telah selesai direvisi dan diundangkan dalam Perbup baru.',
            ],
        ];

        foreach ($revisionSpecs as $index => $spec) {
            $regId = isset($allRegs[$index]) ? $allRegs[$index]->id : ($index + 1);
            RevisionTracking::create([
                'regulation_id' => $regId,
                'status' => $spec['status'],
                'catatan' => $spec['catatan'],
                'ditugaskan_ke' => $hukumUser->id,
                'created_at' => now()->subDays(10 - $index),
                'updated_at' => now(),
            ]);
        }
    }
}
