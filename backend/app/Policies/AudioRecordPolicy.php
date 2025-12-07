<?php

namespace App\Policies;

use App\Models\AudioRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AudioRecordPolicy
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
    public function view(User $user, AudioRecord $audioRecord): bool
    {
        return $user->belongsToTeam($audioRecord->team);
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
    public function update(User $user, AudioRecord $audioRecord): bool
    {
        return $user->belongsToTeam($audioRecord->team)
            && $user->canManageResources($audioRecord->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AudioRecord $audioRecord): bool
    {
        return $user->belongsToTeam($audioRecord->team)
            && $user->canDeleteResources($audioRecord->team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AudioRecord $audioRecord): bool
    {
        return $user->belongsToTeam($audioRecord->team)
            && $user->canDeleteResources($audioRecord->team);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AudioRecord $audioRecord): bool
    {
        return $user->belongsToTeam($audioRecord->team)
            && $user->isTeamOwner($audioRecord->team);
    }
}
