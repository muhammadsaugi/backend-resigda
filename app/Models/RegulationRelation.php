<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegulationRelation extends Model
{
    protected $fillable = [
        'source_id',
        'target_id',
        'jenis_relasi',
        'confidence',
        'alasan',
        'status_tinjau',
        'ditinjau_oleh',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
    ];

    public function source()
    {
        return $this->belongsTo(Regulation::class, 'source_id');
    }

    public function target()
    {
        return $this->belongsTo(Regulation::class, 'target_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }
}