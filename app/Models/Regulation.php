<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regulation extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis',
        'nomor',
        'tahun',
        'judul',
        'opd',
        'tanggal_terbit',
        'status',
        'tags',
        'ringkasan',
        'decay_score',
        'jumlah_dilihat',
        'jumlah_ditanyakan',
        'file_path',
    ];

    protected $casts = [
        'tags' => 'array',
        'tanggal_terbit' => 'date',
        'decay_score' => 'decimal:2',
    ];

    public function articles()
    {
        return $this->hasMany(RegulationArticle::class);
    }

    public function embeddings()
    {
        return $this->hasMany(RegulationEmbedding::class);
    }

    /**
     * Relasi keluar (misal: regulasi ini mencabut/merujuk regulasi lain).
     * Dipakai oleh Conflict Graph Engine (Fase 6).
     */
    public function relationsAsSource()
    {
        return $this->hasMany(RegulationRelation::class, 'source_id');
    }

    public function relationsAsTarget()
    {
        return $this->hasMany(RegulationRelation::class, 'target_id');
    }

    /**
     * Status closed-loop revisi terbaru untuk regulasi ini (Regulatory Decay Tracker).
     * Satu regulasi bisa punya banyak riwayat revision_tracking dari waktu ke waktu,
     * tapi yang relevan untuk dashboard biasanya cuma yang paling baru.
     */
    public function latestRevisionTracking()
    {
        return $this->hasOne(RevisionTracking::class)->latestOfMany();
    }
}