<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireRisque extends Model
{
    protected $fillable = [
        'team_id',
        'client_id',
        'score_global',
        'profil_calcule',
        'recommandation',
    ];

    protected $casts = [
        'score_global' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Scopes\TeamScope);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

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
