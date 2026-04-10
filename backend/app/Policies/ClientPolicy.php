<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Super-admins bypass all policy checks automatically.
     * This runs before every other method.
     */
    public function before(User $user): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null; // Continue to the specific method
    }

    /**
     * Any authenticated user can list clients (the query scope restricts what they see).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A user can view a client if they own it or are a team admin.
     */
    public function view(User $user, Client $client): bool
    {
        return $this->hasAccess($user, $client);
    }

    /**
     * Any authenticated user can create a client.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * A user can update a client if they own it or are a team admin.
     */
    public function update(User $user, Client $client): bool
    {
        return $this->hasAccess($user, $client);
    }

    /**
     * A user can delete a client if they own it or are a team admin.
     */
    public function delete(User $user, Client $client): bool
    {
        return $this->hasAccess($user, $client);
    }

    /**
     * A user can restore a client if they own it or are a team admin.
     */
    public function restore(User $user, Client $client): bool
    {
        return $this->hasAccess($user, $client);
    }

    /**
     * Only team admins can permanently delete a client.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        $team = $user->currentTeam();

        return $team && $user->isTeamAdmin($team);
    }

    /**
     * A user has access to a client if:
     * - they own the client (user_id match), OR
     * - they are an admin of the client's team
     */
    private function hasAccess(User $user, Client $client): bool
    {
        if ($client->user_id === $user->id) {
            return true;
        }

        $team = $user->currentTeam();
        if ($team && $client->team_id === $team->id && $user->isTeamAdmin($team)) {
            return true;
        }

        return false;
    }
}
