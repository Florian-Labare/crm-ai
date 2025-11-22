<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entreprise extends Model
{
    protected $fillable = [
        'client_id',
        'chef_entreprise',
        'statut',
        'travailleur_independant',
        'mandataire_social',
    ];

    protected $casts = [
        'chef_entreprise' => 'boolean',
        'travailleur_independant' => 'boolean',
        'mandataire_social' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
