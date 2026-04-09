<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceDocumentRequirement extends Pivot
{
    protected $table = 'compliance_document_requirements';

    public $incrementing = true;

    protected $fillable = [
        'document_id',
        'requirement_id',
        'status',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ClientComplianceDocument::class, 'document_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class, 'requirement_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
