<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One uploaded calling-data file. */
class LeadBatch extends Model
{
    protected $fillable = ['name', 'file_name', 'uploaded_by', 'total_rows', 'imported', 'duplicates', 'invalid'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'batch_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
