<?php

namespace App\Models;

use App\Scopes\TeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Production extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TeamScope);
    }

    protected $fillable = [
        'team_id',
        'user_id',
        'client_id',
        'nom_client',
        'prenom_client',
        'assureur_id',
        'compagnie_libre',
        'categorie',
        'type_contrat',
        'annee',
        'date_signature',
        'date_effet',
        'prime_ttc',
        'prime_ht',
        'fond_euro',
        'uc',
        'taux_commission',
        'commission_compagnie',
        'commission_mia',
        'commission_recurrente',
        'encours_commission',
        'date_commission',
        'regul_transmise',
        'regul_signee',
        'date_resiliation',
        'date_reprise',
        'statut',
        'notes',
    ];

    protected $casts = [
        'date_signature' => 'date',
        'date_effet' => 'date',
        'date_commission' => 'date',
        'date_resiliation' => 'date',
        'date_reprise' => 'date',
        'regul_transmise' => 'boolean',
        'regul_signee' => 'boolean',
        'prime_ttc' => 'decimal:2',
        'prime_ht' => 'decimal:2',
        'fond_euro' => 'decimal:2',
        'uc' => 'decimal:2',
        'taux_commission' => 'decimal:4',
        'commission_compagnie' => 'decimal:2',
        'commission_mia' => 'decimal:2',
        'commission_recurrente' => 'decimal:2',
        'encours_commission' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assureur(): BelongsTo
    {
        return $this->belongsTo(Assureur::class);
    }
}
