<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminController extends Controller {
    private function authorizeSuperAdmin(): void {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Super admin access required.');
        }
    }

    public function index(): JsonResponse {
        $this->authorizeSuperAdmin();

        $users = User::withCount('teams')->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'teams_count' => $user->teams_count,
            ];
        });

        return response()->json(['users' => $users]);
    }

    public function toggleSuperAdmin(User $user): JsonResponse {
        $this->authorizeSuperAdmin();

        // Prevent removing own super admin status
        if ($user->id === auth()->id()) {
            abort(400, 'Cannot remove your own super admin status.');
        }

        $user->update(['is_super_admin' => ! $user->is_super_admin]);

        return response()->json([
            'message' => 'Super admin status updated.',
            'is_super_admin' => $user->is_super_admin,
        ]);
    }

    public function allTeams(): JsonResponse {
        $this->authorizeSuperAdmin();

        $teams = Team::with('owner')->withCount('users')->get()->map(function (Team $team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'personal_team' => $team->personal_team,
                'owner' => $team->owner ? [
                    'id' => $team->owner->id,
                    'name' => $team->owner->name,
                    'email' => $team->owner->email,
                ] : null,
                'members_count' => $team->users_count,
                'created_at' => $team->created_at,
            ];
        });

        return response()->json(['teams' => $teams]);
    }

    public function deleteTeam(Team $team): JsonResponse {
        $this->authorizeSuperAdmin();

        if ($team->personal_team) {
            abort(400, 'Cannot delete a personal team.');
        }

        $team->delete();

        return response()->json(null, 204);
    }

    public function createTeam(Request $request): JsonResponse {
        $this->authorizeSuperAdmin();

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'personal_team' => false,
        ]);

        auth()->user()->teams()->attach($team, ['role' => 'owner']);

        return response()->json($team, 201);
    }
}
