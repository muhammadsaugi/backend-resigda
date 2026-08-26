<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 3 user ASN contoh, satu per role, untuk testing auth & role middleware.
 * GANTI PASSWORD INI sebelum deployment produksi/demo kompetisi.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Ahmad Fauzi (Staf OPD)',
                'nip' => '198501012010011001',
                'email' => 'staf.opd@sidoarjokab.go.id',
                'role' => 'staf_opd',
                'opd' => 'Dinas Penanaman Modal dan PTSP',
            ],
            [
                'name' => 'Siti Rahma (Bagian Hukum)',
                'nip' => '198703152011012002',
                'email' => 'bagian.hukum@sidoarjokab.go.id',
                'role' => 'bagian_hukum',
                'opd' => 'Bagian Hukum Setda',
            ],
            [
                'name' => 'Budi Santoso (Inspektorat)',
                'nip' => '198209202009011003',
                'email' => 'inspektorat@sidoarjokab.go.id',
                'role' => 'inspektorat',
                'opd' => 'Inspektorat Daerah',
            ],
        ];

        // KEAMANAN: Gunakan USER_SEEDER_PASSWORD di file .env VPS produksi.
        // Di local, fallback ke 'password123' untuk kemudahan development.
        // Di produksi, jika env var tidak di-set, seeder TIDAK boleh berjalan
        // dengan password lemah — akan throw exception.
        $seedPassword = env('USER_SEEDER_PASSWORD');
        if (! $seedPassword) {
            if (app()->environment('production')) {
                throw new \RuntimeException(
                    'USER_SEEDER_PASSWORD harus di-set di .env sebelum menjalankan seeder di produksi.'
                );
            }
            $seedPassword = 'password123'; // hanya untuk local/testing
        }

        foreach ($users as $userData) {
            User::create([
                ...$userData,
                'password' => Hash::make($seedPassword),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}