<?php

namespace App\Http\Controllers;

use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * Restricted to super admins and team admins
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $currentTeam = $user->currentTeam();

        // Un user sans cabinet peut créer le sien (onboarding)
        // Un user avec cabinet doit être admin/owner ou super admin pour en créer un nouveau
        $hasTeam = $currentTeam !== null;
        if ($hasTeam && ! $user->isSuperAdmin() && ! $user->isTeamAdmin($currentTeam)) {
            abort(403, 'Seuls les super admins et admins de cabinet peuvent créer un nouveau cabinet.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'personal_team' => false,
        ]);

        $user->teams()->attach($team, ['role' => 'owner']);

        return response()->json($team, 201);
    }

    /**
     * Display the specified team
     */
    public function show(Team $team): JsonResponse
    {
        if (! auth()->user()->belongsToTeam($team)) {
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
        if (! auth()->user()->isTeamAdmin($team)) {
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
        if (! auth()->user()->isTeamOwner($team)) {
            abort(403, 'Only the team owner can delete the team.');
        }

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
        if (! auth()->user()->belongsToTeam($team)) {
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
     * Invite a member to the team via email
     */
    public function inviteMember(Request $request, Team $team): JsonResponse
    {
        if (! auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can invite members.');
        }

        $request->validate([
            'email' => 'required|email',
            'role' => ['required', Rule::in(['admin', 'mia', 'secretaire'])],
        ]);

        $email = $request->email;
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            // User already exists → attach directly
            if ($existingUser->belongsToTeam($team)) {
                return response()->json(['message' => 'Cet utilisateur est déjà membre de ce cabinet.'], 400);
            }

            $existingUser->teams()->attach($team, ['role' => $request->role]);

            return response()->json([
                'message' => 'Membre ajouté avec succès.',
                'member' => [
                    'id' => $existingUser->id,
                    'name' => $existingUser->name,
                    'email' => $existingUser->email,
                    'role' => $request->role,
                ],
            ], 201);
        }

        // New user → create invitation
        // Cancel any existing pending invitation for this email+team
        TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'email' => $email,
            'role' => $request->role,
            'token' => Str::uuid()->toString(),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load(['team', 'inviter']);

        Mail::to($email)->send(new TeamInvitationMail($invitation));

        return response()->json([
            'message' => 'Invitation envoyée par email.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
            ],
        ], 201);
    }

    /**
     * Show invitation details (public)
     */
    public function showInvitation(string $token): JsonResponse
    {
        $invitation = TeamInvitation::where('token', $token)
            ->with(['team', 'inviter'])
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return response()->json(['message' => 'Cette invitation a expiré.'], 410);
        }

        if (! is_null($invitation->accepted_at)) {
            return response()->json(['message' => 'Cette invitation a déjà été acceptée.'], 410);
        }

        return response()->json([
            'token' => $invitation->token,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'team' => [
                'id' => $invitation->team->id,
                'name' => $invitation->team->name,
            ],
            'invited_by' => $invitation->inviter?->name,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    /**
     * Accept an invitation (auth required)
     */
    public function acceptInvitation(string $token): JsonResponse
    {
        $user = auth()->user();

        $invitation = TeamInvitation::where('token', $token)
            ->with('team')
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return response()->json(['message' => 'Cette invitation a expiré.'], 410);
        }

        if (! is_null($invitation->accepted_at)) {
            return response()->json(['message' => 'Cette invitation a déjà été acceptée.'], 410);
        }

        if ($user->belongsToTeam($invitation->team)) {
            return response()->json(['message' => 'Vous êtes déjà membre de ce cabinet.'], 400);
        }

        $invitation->team->users()->attach($user, ['role' => $invitation->role]);
        $invitation->update(['accepted_at' => now()]);

        return response()->json([
            'message' => 'Invitation acceptée. Vous avez rejoint '.$invitation->team->name.'.',
            'team' => [
                'id' => $invitation->team->id,
                'name' => $invitation->team->name,
            ],
        ]);
    }

    /**
     * Decline/delete an invitation (public)
     */
    public function declineInvitation(string $token): JsonResponse
    {
        $invitation = TeamInvitation::where('token', $token)->firstOrFail();
        $invitation->delete();

        return response()->json(['message' => 'Invitation refusée.']);
    }

    /**
     * List pending invitations for a team (admin only)
     */
    public function pendingInvitations(Team $team): JsonResponse
    {
        if (! auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can view invitations.');
        }

        $invitations = $team->invitations()->pending()->with('inviter')->get()->map(function ($inv) {
            return [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'invited_by' => $inv->inviter?->name,
                'expires_at' => $inv->expires_at,
                'created_at' => $inv->created_at,
            ];
        });

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Cancel a pending invitation (admin only)
     */
    public function cancelInvitation(Team $team, TeamInvitation $invitation): JsonResponse
    {
        if (! auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can cancel invitations.');
        }

        if ($invitation->team_id !== $team->id) {
            abort(404);
        }

        $invitation->delete();

        return response()->json(['message' => 'Invitation annulée.']);
    }

    /**
     * Update member role
     */
    public function updateMemberRole(Request $request, Team $team, User $user): JsonResponse
    {
        if (! auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can update member roles.');
        }

        if ($user->isTeamOwner($team)) {
            abort(400, 'Cannot change the owner\'s role.');
        }

        $request->validate([
            'role' => ['required', Rule::in(['admin', 'mia', 'secretaire'])],
        ]);

        $team->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member role updated successfully.',
        ]);
    }

    /**
     * Upload logo for the team (admin only)
     */
    public function uploadLogo(Request $request, int $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);

        if (! auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can upload a logo.');
        }

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        // Delete previous logo if exists
        if ($team->logo_path) {
            Storage::disk('s3')->delete($team->logo_path);
        }

        $file = $request->file('logo');
        $ext = $file->getClientOriginalExtension();
        $s3Path = "logos/team_{$teamId}.{$ext}";

        Storage::disk('s3')->put($s3Path, file_get_contents($file->getRealPath()), 'public');

        $team->update(['logo_path' => $s3Path]);

        return response()->json(['logo_url' => $team->logo_url]);
    }

    /**
     * Remove a member from the team
     */
    public function removeMember(Team $team, User $user): JsonResponse
    {
        if (! auth()->user()->isTeamAdmin($team)) {
            abort(403, 'Only team admins can remove members.');
        }

        if ($user->isTeamOwner($team)) {
            abort(400, 'Cannot remove the team owner.');
        }

        $user->teams()->detach($team);

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }
}
