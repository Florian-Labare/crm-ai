<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * Display a listing of user's teams
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $teams = $user->teams()->with('owner')->get();

        return response()->json([
            'teams' => $teams,
            'current_team' => $user->currentTeam(),
        ]);
    }

    /**
     * Store a newly created team
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'personal_team' => false,
        ]);

        // Attach creator as owner
        auth()->user()->teams()->attach($team, ['role' => 'owner']);

        return response()->json($team, 201);
    }

    /**
     * Display the specified team
     */
    public function show(Team $team): JsonResponse
    {
        // Check if user belongs to team
        if (!auth()->user()->belongsToTeam($team)) {
            abort(403, 'You do not belong to this team.');
        }

        $team->load(['owner', 'users']);

        return response()->json($team);
    }

    /**
     * Update the specified team
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        // Only owner or admin can update team
        if (!auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can update the team.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team->update($request->only('name'));

        return response()->json($team);
    }

    /**
     * Remove the specified team
     */
    public function destroy(Team $team): JsonResponse
    {
        // Only owner can delete team
        if (!auth()->user()->isTeamOwner($team)) {
            abort(403, 'Only the team owner can delete the team.');
        }

        // Cannot delete personal team
        if ($team->personal_team) {
            abort(400, 'Cannot delete personal team.');
        }

        $team->delete();

        return response()->json(null, 204);
    }

    /**
     * Get team members
     */
    public function members(Team $team): JsonResponse
    {
        // Check if user belongs to team
        if (!auth()->user()->belongsToTeam($team)) {
            abort(403, 'You do not belong to this team.');
        }

        $members = $team->users()
            ->withPivot('role')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'joined_at' => $user->pivot->created_at,
                ];
            });

        return response()->json(['members' => $members]);
    }

    /**
     * Invite a member to the team
     */
    public function inviteMember(Request $request, Team $team): JsonResponse
    {
        // Only owner or admin can invite members
        if (!auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can invite members.');
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => ['required', Rule::in(['admin', 'member', 'viewer'])],
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if user already in team
        if ($user->belongsToTeam($team)) {
            return response()->json([
                'message' => 'User is already a member of this team.',
            ], 400);
        }

        // Attach user to team
        $user->teams()->attach($team, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member invited successfully.',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ], 201);
    }

    /**
     * Update member role
     */
    public function updateMemberRole(Request $request, Team $team, User $user): JsonResponse
    {
        // Only owner or admin can update roles
        if (!auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can update member roles.');
        }

        // Cannot change owner's role
        if ($user->isTeamOwner($team)) {
            abort(400, 'Cannot change the owner\'s role.');
        }

        $request->validate([
            'role' => ['required', Rule::in(['admin', 'member', 'viewer'])],
        ]);

        // Update role
        $team->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member role updated successfully.',
        ]);
    }

    /**
     * Remove a member from the team
     */
    public function removeMember(Team $team, User $user): JsonResponse
    {
        // Only owner or admin can remove members
        if (!auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can remove members.');
        }

        // Cannot remove owner
        if ($user->isTeamOwner($team)) {
            abort(400, 'Cannot remove the team owner.');
        }

        // Detach user from team
        $user->teams()->detach($team);

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }
}
