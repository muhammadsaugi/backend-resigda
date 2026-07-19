<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionHistory extends Model
{
    // Sama seperti RevisionTracking — Eloquent akan menebak "revision_histories"
    // (pluralisasi bahasa Inggris mengubah "history" jadi "histories"),
    // padahal tabelnya bernama "revision_history" (singular).
    protected $table = 'revision_history';

    protected $fillable = [
        'revision_tracking_id',
        'status',
        'catatan',
        'created_by',
    ];

    public function revisionTracking()
    {
        return $this->belongsTo(RevisionTracking::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}