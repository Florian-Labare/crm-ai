<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'nom','prenom','datedenaissance','lieudenaissance',
        'situationmatrimoniale','profession','revenusannuels',
        'nombreenfants','besoins','transcription_path','consentement_audio'
    ];

    protected $casts = [
        'datedenaissance'    => 'date',
        'revenusannuels'     => 'decimal:2',
        'nombreenfants'      => 'integer',
        'besoins'            => 'array',
        'consentement_audio' => 'boolean',
    ];
}
