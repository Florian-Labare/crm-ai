<?php

namespace App\Traits;

use App\Models\Team;

trait HasTeams
{
    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams->contains(function ($t) use ($team) {
            return $t->id === $team->id;
        });
    }

    /**
     * The teams that the user belongs to.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The team that the user owns.
     */
    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    /**
     * Get the user's current team.
     * For now, we return the first team or create one if none exists.
     */
    public function currentTeam()
    {
        // In a real app, we would store current_team_id in the user table
        // For now, we'll return the personal team
        return $this->ownedTeams()->where('personal_team', true)->first()
            ?? $this->ownedTeams()->first()
            ?? $this->teams()->first();
    }

    /**
     * Get user's role in a specific team
     */
    public function roleInTeam(Team $team): ?string
    {
        return $this->teams()
            ->where('team_id', $team->id)
            ->first()?->pivot?->role;
    }

    /**
     * Check if user has a specific role in a team
     */
    public function hasTeamRole(Team $team, string|array $roles): bool
    {
        $userRole = $this->roleInTeam($team);

        if (is_array($roles)) {
            return in_array($userRole, $roles);
        }

        return $userRole === $roles;
    }

    /**
     * Check if user is owner of the team
     */
    public function isTeamOwner(Team $team): bool
    {
        return $this->hasTeamRole($team, 'owner');
    }

    /**
     * Check if user is admin or owner of the team
     */
    public function isTeamAdmin(Team $team): bool
    {
        return $this->hasTeamRole($team, ['owner', 'admin']);
    }

    /**
     * Check if user can manage resources (owner, admin, or member)
     */
    public function canManageResources(Team $team): bool
    {
        return $this->hasTeamRole($team, ['owner', 'admin', 'member']);
    }

    /**
     * Check if user can delete resources (owner or admin only)
     */
    public function canDeleteResources(Team $team): bool
    {
        return $this->hasTeamRole($team, ['owner', 'admin']);
    }
}
