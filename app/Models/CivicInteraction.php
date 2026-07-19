<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PRIVACY BY DESIGN: model ini tidak boleh diberi kolom $fillable
 * untuk teks pertanyaan asli, IP, atau identitas apapun.
 * Lihat komentar di migration create_civic_interactions_table.
 */
class CivicInteraction extends Model
{
    protected $fillable = [
        'session_id',
        'citizen_id',
        'topic',
        'sentiment',
        'regulation_ids',
        'confidence_score',
        'kecamatan',
        'interacted_at',
    ];

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    protected $casts = [
        'regulation_ids' => 'array',
        'confidence_score' => 'decimal:2',
        'interacted_at' => 'datetime',
    ];
}