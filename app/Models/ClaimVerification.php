<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimVerification extends Model
{
    protected $fillable = [
        'session_id',
        'citizen_id',
        'klaim_text',
        'hasil_verifikasi',
        'regulation_ids',
        'kecamatan',
        'layanan',
    ];

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    protected $casts = [
        'regulation_ids' => 'array',
    ];
}