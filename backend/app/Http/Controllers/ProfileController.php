<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile (name, firstname)
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'firstname' => 'required|string|max:255',
            'name' => 'required|string|max:255',
        ]);

        $user->update($request->only('firstname', 'name'));

        return response()->json([
            'message' => 'Profil mis à jour.',
            'firstname' => $user->firstname,
            'name' => $user->name,
        ]);
    }

    /**
     * Upload avatar photo for the authenticated user
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'avatar' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($user->avatar_path) {
            Storage::disk('s3')->delete($user->avatar_path);
        }

        $file = $request->file('avatar');
        $ext = $file->getClientOriginalExtension();
        $s3Path = "avatars/user_{$user->id}.{$ext}";

        Storage::disk('s3')->put($s3Path, file_get_contents($file->getRealPath()), 'public');

        $user->update(['avatar_path' => $s3Path]);

        return response()->json(['avatar_url' => $user->avatar_url]);
    }

    /**
     * Remove avatar photo for the authenticated user
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('s3')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json(['avatar_url' => null]);
    }

    /**
     * Update the authenticated user's password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }
}
