<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan: kolom `embedding` (tipe pgvector) sengaja tidak di-cast di sini.
 * Operasi vector search (similarity, insert vector) sebaiknya dilakukan
 * lewat FastAPI menggunakan psycopg2/pgvector Python client secara langsung,
 * bukan lewat Eloquent — karena Eloquent tidak punya native support untuk
 * tipe vector dan query cosine similarity.
 *
 * Model ini tetap berguna untuk Laravel membaca metadata (regulation_id,
 * article_id, content_chunk) saat menampilkan data di admin panel.
 */
class RegulationEmbedding extends Model
{
    protected $fillable = [
        'regulation_id',
        'article_id',
        'content_chunk',
    ];

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function article()
    {
        return $this->belongsTo(RegulationArticle::class, 'article_id');
    }
}