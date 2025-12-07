<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAutreEpargne extends Model
{
    protected $table = 'client_autres_epargnes';

    protected $fillable = [
        'client_id',
        'designation',
        'detenteur',
        'valeur',
    ];

    protected $casts = [
        'valeur' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
