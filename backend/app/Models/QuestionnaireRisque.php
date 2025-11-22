<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireRisque extends Model
{
    protected $fillable = [
        'client_id',
        'score_global',
        'profil_calcule',
        'recommandation',
    ];

    protected $casts = [
        'score_global' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function financier(): HasOne
    {
        return $this->hasOne(QuestionnaireRisqueFinancier::class);
    }

    public function connaissances(): HasOne
    {
        return $this->hasOne(QuestionnaireRisqueConnaissance::class);
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(QuestionnaireRisqueQuiz::class);
    }
}
