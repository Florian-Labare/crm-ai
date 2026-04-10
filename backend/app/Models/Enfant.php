<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enfant extends Model
{
    protected $fillable = [
        'client_id',
        'nom',
        'prenom',
        'date_naissance',
        'fiscalement_a_charge',
        'garde_alternee',
    ];

    protected $casts = [
        'fiscalement_a_charge' => 'boolean',
        'garde_alternee' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
