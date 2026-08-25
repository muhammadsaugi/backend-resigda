<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimVerification extends Model
{
    protected $fillable = [
        'session_id',
        'citizen_id',
        'klaim_text',
        'foto_bukti',
        'hasil_verifikasi',
        'regulation_ids',
        'kecamatan',
        'layanan',
        'kategori_laporan',
        'dilaporkan_ke_inspektorat',
        'catatan_laporan',
        'status_audit',
    ];

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    protected $casts = [
        'regulation_ids' => 'array',
        'dilaporkan_ke_inspektorat' => 'boolean',
    ];
}