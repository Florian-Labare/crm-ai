<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam() !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $user->belongsToTeam($documentTemplate->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam();
        return $team && $user->canManageResources($team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $user->belongsToTeam($documentTemplate->team)
            && $user->canManageResources($documentTemplate->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $user->belongsToTeam($documentTemplate->team)
            && $user->canDeleteResources($documentTemplate->team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $user->belongsToTeam($documentTemplate->team)
            && $user->canDeleteResources($documentTemplate->team);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $user->belongsToTeam($documentTemplate->team)
            && $user->isTeamOwner($documentTemplate->team);
    }
}
