<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All team members can view clients
        return $user->currentTeam() !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Client $client): bool
    {
        // User must belong to the client's team
        return $user->belongsToTeam($client->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam();

        // Owner, admin, or member can create clients
        return $team && $user->canManageResources($team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Client $client): bool
    {
        // User must belong to team and have permission to manage resources
        return $user->belongsToTeam($client->team)
            && $user->canManageResources($client->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Client $client): bool
    {
        // Only owner and admin can delete clients
        return $user->belongsToTeam($client->team)
            && $user->canDeleteResources($client->team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Client $client): bool
    {
        // Only owner and admin can restore clients
        return $user->belongsToTeam($client->team)
            && $user->canDeleteResources($client->team);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        // Only owner can force delete
        return $user->belongsToTeam($client->team)
            && $user->isTeamOwner($client->team);
    }
}
