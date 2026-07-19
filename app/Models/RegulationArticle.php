<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegulationArticle extends Model
{
    protected $fillable = [
        'regulation_id',
        'nomor_pasal',
        'isi',
    ];

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function embeddings()
    {
        return $this->hasMany(RegulationEmbedding::class, 'article_id');
    }
}