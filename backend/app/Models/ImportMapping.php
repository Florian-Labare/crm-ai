<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportMapping extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'source_type',
        'column_mappings',
        'default_values',
    ];

    protected $casts = [
        'column_mappings' => 'array',
        'default_values' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ImportSession::class);
    }
}
