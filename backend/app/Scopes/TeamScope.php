<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TeamScope implements Scope {
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void {
        if (Auth::hasUser()) {
            $currentTeam = Auth::user()->currentTeam();

            // Only apply scope if user has a team
            if ($currentTeam) {
                $builder->where('team_id', $currentTeam->id);
            }
        }
    }
}
