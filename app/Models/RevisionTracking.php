<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionTracking extends Model
{
    // Eloquent secara default menebak nama tabel "revision_trackings"
    // (dipluralkan otomatis dari nama model), padahal migration membuat
    // tabel bernama "revision_tracking" (singular). Wajib dieksplisitkan.
    protected $table = 'revision_tracking';

    protected $fillable = [
        'regulation_id',
        'status',
        'catatan',
        'ditugaskan_ke',
    ];

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'ditugaskan_ke');
    }

    public function history()
    {
        return $this->hasMany(RevisionHistory::class)->latest();
    }
}