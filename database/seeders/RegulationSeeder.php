<?php

namespace Database\Seeders;

use App\Models\Regulation;
use Illuminate\Database\Seeder;

/**
 * Data dummy untuk prototipe/demo REGSIDA.
 * Judul & nomor mengikuti pola penamaan JDIH Kabupaten Sidoarjo,
 * namun ISI/RINGKASAN adalah contoh generik — WAJIB diganti dengan
 * data asli JDIH sebelum produksi (lihat master prompt, bagian
 * "Tentang data regulasi").
 */
class RegulationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // ===== PERDA (5) =====
            [
                'jenis' => 'perda', 'nomor' => '4', 'tahun' => 2022,
                'judul' => 'Peraturan Daerah Kabupaten Sidoarjo Nomor 4 Tahun 2022 tentang Retribusi Jasa Umum',
                'opd' => 'Badan Pendapatan Daerah',
                'tanggal_terbit' => '2022-03-15', 'status' => 'berlaku',
                'tags' => ['retribusi', 'pendapatan daerah', 'jasa umum'],
                'ringkasan' => 'Mengatur jenis, tarif, dan tata cara pemungutan retribusi atas jasa umum yang disediakan pemerintah daerah kepada warga.',
            ],
            [
                'jenis' => 'perda', 'nomor' => '7', 'tahun' => 2021,
                'judul' => 'Peraturan Daerah Kabupaten Sidoarjo Nomor 7 Tahun 2021 tentang Penyelenggaraan Perizinan Berusaha Berbasis Risiko',
                'opd' => 'Dinas Penanaman Modal dan PTSP',
                'tanggal_terbit' => '2021-08-10', 'status' => 'berlaku',
                'tags' => ['perizinan', 'investasi', 'OSS'],
                'ringkasan' => 'Mengatur mekanisme perizinan berusaha berbasis risiko sesuai kewenangan daerah, selaras dengan sistem OSS nasional.',
            ],
            [
                'jenis' => 'perda', 'nomor' => '2', 'tahun' => 2020,
                'judul' => 'Peraturan Daerah Kabupaten Sidoarjo Nomor 2 Tahun 2020 tentang Pengelolaan Sampah',
                'opd' => 'Dinas Lingkungan Hidup',
                'tanggal_terbit' => '2020-02-20', 'status' => 'berlaku',
                'tags' => ['sampah', 'lingkungan hidup'],
                'ringkasan' => 'Mengatur pengelolaan sampah rumah tangga dan sejenisnya, termasuk peran bank sampah dan tanggung jawab produsen.',
            ],
            [
                'jenis' => 'perda', 'nomor' => '9', 'tahun' => 2019,
                'judul' => 'Peraturan Daerah Kabupaten Sidoarjo Nomor 9 Tahun 2019 tentang Rencana Tata Ruang Wilayah 2019-2039',
                'opd' => 'Dinas Pekerjaan Umum dan Penataan Ruang',
                'tanggal_terbit' => '2019-11-05', 'status' => 'berlaku',
                'tags' => ['tata ruang', 'RTRW', 'pembangunan'],
                'ringkasan' => 'Menetapkan rencana struktur dan pola ruang wilayah Kabupaten Sidoarjo untuk periode 20 tahun.',
            ],
            [
                'jenis' => 'perda', 'nomor' => '1', 'tahun' => 2023,
                'judul' => 'Peraturan Daerah Kabupaten Sidoarjo Nomor 1 Tahun 2023 tentang Penyelenggaraan Pendidikan',
                'opd' => 'Dinas Pendidikan',
                'tanggal_terbit' => '2023-01-18', 'status' => 'berlaku',
                'tags' => ['pendidikan', 'sekolah', 'wajib belajar'],
                'ringkasan' => 'Mengatur penyelenggaraan pendidikan dasar dan menengah di wilayah Kabupaten Sidoarjo termasuk pembiayaan dan zonasi.',
            ],

            // ===== PERBUP (5) =====
            [
                'jenis' => 'perbup', 'nomor' => '35', 'tahun' => 2023,
                'judul' => 'Peraturan Bupati Sidoarjo Nomor 35 Tahun 2023 tentang Tata Cara Pemberian Izin Mendirikan Bangunan',
                'opd' => 'Dinas Penanaman Modal dan PTSP',
                'tanggal_terbit' => '2023-04-12', 'status' => 'berlaku',
                'tags' => ['IMB', 'bangunan', 'perizinan'],
                'ringkasan' => 'Menjabarkan teknis pengajuan, verifikasi, dan penerbitan izin mendirikan bangunan di Kabupaten Sidoarjo.',
            ],
            [
                'jenis' => 'perbup', 'nomor' => '12', 'tahun' => 2022,
                'judul' => 'Peraturan Bupati Sidoarjo Nomor 12 Tahun 2022 tentang Standar Pelayanan Publik',
                'opd' => 'Bagian Organisasi Setda',
                'tanggal_terbit' => '2022-02-01', 'status' => 'berlaku',
                'tags' => ['pelayanan publik', 'standar layanan'],
                'ringkasan' => 'Menetapkan standar minimal pelayanan publik yang wajib dipenuhi seluruh OPD di Kabupaten Sidoarjo.',
            ],
            [
                'jenis' => 'perbup', 'nomor' => '20', 'tahun' => 2021,
                'judul' => 'Peraturan Bupati Sidoarjo Nomor 20 Tahun 2021 tentang Pengelolaan Bantuan Sosial',
                'opd' => 'Dinas Sosial',
                'tanggal_terbit' => '2021-05-19', 'status' => 'berlaku',
                'tags' => ['bansos', 'kemiskinan', 'jaminan sosial'],
                'ringkasan' => 'Mengatur kriteria penerima, mekanisme penyaluran, dan pengawasan bantuan sosial daerah.',
            ],
            [
                'jenis' => 'perbup', 'nomor' => '8', 'tahun' => 2020,
                'judul' => 'Peraturan Bupati Sidoarjo Nomor 8 Tahun 2020 tentang Tarif Retribusi Pelayanan Kesehatan Puskesmas',
                'opd' => 'Dinas Kesehatan',
                'tanggal_terbit' => '2020-01-22', 'status' => 'diubah',
                'tags' => ['kesehatan', 'puskesmas', 'tarif'],
                'ringkasan' => 'Menetapkan besaran tarif layanan kesehatan di Puskesmas se-Kabupaten Sidoarjo. Sebagian pasal telah diubah perbup turunan.',
            ],
            [
                'jenis' => 'perbup', 'nomor' => '44', 'tahun' => 2023,
                'judul' => 'Peraturan Bupati Sidoarjo Nomor 44 Tahun 2023 tentang Pedoman Pengelolaan Bank Sampah',
                'opd' => 'Dinas Lingkungan Hidup',
                'tanggal_terbit' => '2023-09-08', 'status' => 'berlaku',
                'tags' => ['bank sampah', 'lingkungan', 'pengelolaan sampah'],
                'ringkasan' => 'Menjabarkan teknis pendirian dan operasional bank sampah sebagai turunan Perda Pengelolaan Sampah.',
            ],

            // ===== SURAT EDARAN (5) =====
            [
                'jenis' => 'se', 'nomor' => '440/1123', 'tahun' => 2024,
                'judul' => 'Surat Edaran Bupati Sidoarjo Nomor 440/1123 Tahun 2024 tentang Kesiapsiagaan Penanganan DBD',
                'opd' => 'Dinas Kesehatan',
                'tanggal_terbit' => '2024-02-14', 'status' => 'berlaku',
                'tags' => ['kesehatan', 'DBD', 'kesiapsiagaan'],
                'ringkasan' => 'Mengimbau seluruh OPD dan fasilitas kesehatan meningkatkan kewaspadaan dini terhadap peningkatan kasus DBD musiman.',
            ],
            [
                'jenis' => 'se', 'nomor' => '060/875', 'tahun' => 2023,
                'judul' => 'Surat Edaran Sekretaris Daerah Nomor 060/875 Tahun 2023 tentang Jam Kerja Bulan Ramadan',
                'opd' => 'Bagian Organisasi Setda',
                'tanggal_terbit' => '2023-03-01', 'status' => 'dicabut',
                'tags' => ['jam kerja', 'ramadan', 'ASN'],
                'ringkasan' => 'Mengatur penyesuaian jam kerja ASN selama bulan Ramadan. Berlaku terbatas dan sudah tidak berlaku pasca periode dimaksud.',
            ],
            [
                'jenis' => 'se', 'nomor' => '503/221', 'tahun' => 2024,
                'judul' => 'Surat Edaran Kepala DPMPTSP Nomor 503/221 Tahun 2024 tentang Percepatan Layanan Perizinan Online',
                'opd' => 'Dinas Penanaman Modal dan PTSP',
                'tanggal_terbit' => '2024-01-10', 'status' => 'berlaku',
                'tags' => ['perizinan', 'digitalisasi', 'OSS'],
                'ringkasan' => 'Mengimbau percepatan proses verifikasi izin melalui sistem daring dan pembatasan tatap muka untuk layanan tertentu.',
            ],
            [
                'jenis' => 'se', 'nomor' => '900/456', 'tahun' => 2023,
                'judul' => 'Surat Edaran Bupati Sidoarjo Nomor 900/456 Tahun 2023 tentang Efisiensi Belanja Daerah',
                'opd' => 'Badan Pengelolaan Keuangan dan Aset Daerah',
                'tanggal_terbit' => '2023-07-03', 'status' => 'berlaku',
                'tags' => ['anggaran', 'efisiensi', 'belanja daerah'],
                'ringkasan' => 'Mengimbau seluruh OPD melakukan efisiensi belanja non-prioritas menyusul penyesuaian target pendapatan daerah.',
            ],
            [
                'jenis' => 'se', 'nomor' => '421/998', 'tahun' => 2024,
                'judul' => 'Surat Edaran Kepala Dinas Pendidikan Nomor 421/998 Tahun 2024 tentang Pencegahan Perundungan di Sekolah',
                'opd' => 'Dinas Pendidikan',
                'tanggal_terbit' => '2024-05-20', 'status' => 'berlaku',
                'tags' => ['pendidikan', 'anti-bullying', 'sekolah'],
                'ringkasan' => 'Mengimbau seluruh satuan pendidikan membentuk tim pencegahan perundungan dan menyediakan kanal pelaporan bagi siswa.',
            ],

            // ===== INSTRUKSI BUPATI (5) =====
            [
                'jenis' => 'instruksi_bupati', 'nomor' => '3', 'tahun' => 2024,
                'judul' => 'Instruksi Bupati Sidoarjo Nomor 3 Tahun 2024 tentang Pemberantasan Pungutan Liar dalam Pelayanan Publik',
                'opd' => 'Inspektorat Daerah',
                'tanggal_terbit' => '2024-03-05', 'status' => 'berlaku',
                'tags' => ['pungli', 'anti korupsi', 'pengawasan'],
                'ringkasan' => 'Menginstruksikan seluruh OPD untuk menutup celah pungutan liar dan menyediakan kanal pengaduan yang mudah diakses warga.',
            ],
            [
                'jenis' => 'instruksi_bupati', 'nomor' => '1', 'tahun' => 2023,
                'judul' => 'Instruksi Bupati Sidoarjo Nomor 1 Tahun 2023 tentang Percepatan Digitalisasi Layanan Pemerintahan',
                'opd' => 'Dinas Komunikasi dan Informatika',
                'tanggal_terbit' => '2023-01-30', 'status' => 'berlaku',
                'tags' => ['digitalisasi', 'e-government', 'transformasi digital'],
                'ringkasan' => 'Menginstruksikan seluruh OPD mempercepat migrasi layanan manual ke platform digital terintegrasi.',
            ],
            [
                'jenis' => 'instruksi_bupati', 'nomor' => '5', 'tahun' => 2022,
                'judul' => 'Instruksi Bupati Sidoarjo Nomor 5 Tahun 2022 tentang Disiplin Protokol Kesehatan di Fasilitas Publik',
                'opd' => 'Dinas Kesehatan',
                'tanggal_terbit' => '2022-06-15', 'status' => 'dicabut',
                'tags' => ['kesehatan', 'protokol', 'fasilitas publik'],
                'ringkasan' => 'Menginstruksikan pengelola fasilitas publik menegakkan protokol kesehatan. Sudah tidak relevan pasca pencabutan status darurat.',
            ],
            [
                'jenis' => 'instruksi_bupati', 'nomor' => '2', 'tahun' => 2024,
                'judul' => 'Instruksi Bupati Sidoarjo Nomor 2 Tahun 2024 tentang Optimalisasi Penyerapan Anggaran Belanja Daerah',
                'opd' => 'Badan Pengelolaan Keuangan dan Aset Daerah',
                'tanggal_terbit' => '2024-02-01', 'status' => 'berlaku',
                'tags' => ['anggaran', 'APBD', 'penyerapan belanja'],
                'ringkasan' => 'Menginstruksikan percepatan pelaksanaan program dan kegiatan agar penyerapan anggaran sesuai target triwulanan.',
            ],
            [
                'jenis' => 'instruksi_bupati', 'nomor' => '4', 'tahun' => 2023,
                'judul' => 'Instruksi Bupati Sidoarjo Nomor 4 Tahun 2023 tentang Penanganan Stunting Terintegrasi',
                'opd' => 'Dinas Kesehatan',
                'tanggal_terbit' => '2023-08-22', 'status' => 'berlaku',
                'tags' => ['stunting', 'kesehatan', 'gizi anak'],
                'ringkasan' => 'Menginstruksikan seluruh OPD terkait berkolaborasi dalam program percepatan penurunan stunting berbasis data kecamatan.',
            ],
        ];

        foreach ($data as $item) {
            Regulation::create($item);
        }
    }
}