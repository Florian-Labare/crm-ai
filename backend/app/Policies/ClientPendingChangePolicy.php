<?php

namespace App\Policies;

use App\Models\ClientPendingChange;
use App\Models\User;

class ClientPendingChangePolicy
{
    /**
     * L'utilisateur peut voir le pending change s'il en est le propriétaire
     */
    public function view(User $user, ClientPendingChange $pendingChange): bool
    {
        return $user->id === $pendingChange->user_id;
    }

    /**
     * L'utilisateur peut modifier le pending change s'il en est le propriétaire
     */
    public function update(User $user, ClientPendingChange $pendingChange): bool
    {
        return $user->id === $pendingChange->user_id;
    }

    /**
     * L'utilisateur peut supprimer le pending change s'il en est le propriétaire
     */
    public function delete(User $user, ClientPendingChange $pendingChange): bool
    {
        return $user->id === $pendingChange->user_id;
    }
}
