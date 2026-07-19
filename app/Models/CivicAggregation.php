<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CivicAggregation extends Model
{
    protected $fillable = [
        'periode',
        'topic',
        'sentiment',
        'jumlah_interaksi',
        'regulation_ids',
        'kecamatan',
    ];

    protected $casts = [
        'periode' => 'date',
        'regulation_ids' => 'array',
    ];
}