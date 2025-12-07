<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientActifFinancier extends Model
{
    protected $table = 'client_actifs_financiers';

    protected $fillable = [
        'client_id',
        'nature',
        'etablissement',
        'detenteur',
        'date_ouverture_souscription',
        'valeur_actuelle',
    ];

    protected $casts = [
        'date_ouverture_souscription' => 'date',
        'valeur_actuelle' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
