<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assureur extends Model
{
    protected $fillable = [
        'nom',
        'lien_espace_client',
    ];

    public function contrats(): HasMany
    {
        return $this->hasMany(ClientContrat::class);
    }
}
