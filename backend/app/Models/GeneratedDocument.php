<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'document_template_id',
        'file_path',
        'format',
        'sent_by_email',
        'sent_at',
    ];

    protected $casts = [
        'sent_by_email' => 'boolean',
        'sent_at' => 'datetime',
    ];

    /**
     * Un document généré appartient à un client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Un document généré appartient à un utilisateur (qui l'a généré)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un document généré appartient à un template
     */
    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /**
     * Marquer le document comme envoyé
     */
    public function markAsSent(): void
    {
        $this->update([
            'sent_by_email' => true,
            'sent_at' => now(),
        ]);
    }

    /**
     * Scope pour récupérer uniquement les documents envoyés
     */
    public function scopeSent($query)
    {
        return $query->where('sent_by_email', true);
    }

    /**
     * Scope pour récupérer uniquement les documents non envoyés
     */
    public function scopeNotSent($query)
    {
        return $query->where('sent_by_email', false);
    }
}
