<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContrat extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'assureur_id',
        'mensualite',
        'en_cours',
        'fond_euro',
        'uc',
        'versement_programme',
    ];

    protected $casts = [
        'mensualite' => 'float',
        'en_cours' => 'float',
        'fond_euro' => 'float',
        'uc' => 'float',
        'versement_programme' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assureur(): BelongsTo
    {
        return $this->belongsTo(Assureur::class);
    }
}
