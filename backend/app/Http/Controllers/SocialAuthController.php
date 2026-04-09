<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'azure'];

    /**
     * Initie le flux OAuth pour lier un compte social à un user déjà authentifié.
     * Retourne l'URL de redirection OAuth (le frontend navigue vers cette URL).
     */
    public function initiateLink(string $provider): JsonResponse
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS)) {
            abort(404);
        }

        $userId  = auth()->id();
        $expires = now()->addMinutes(10)->timestamp;
        $sig     = hash_hmac('sha256', "{$userId}.{$expires}", config('app.key'));
        $state   = base64_encode(json_encode([
            'mode' => 'link',
            'uid'  => $userId,
            'exp'  => $expires,
            'sig'  => $sig,
        ]));

        if ($provider === 'google') {
            $url = Socialite::driver('google')
                ->scopes(['https://www.googleapis.com/auth/gmail.send'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent', 'state' => $state])
                ->stateless()
                ->redirect()
                ->getTargetUrl();
            return response()->json(['redirect_url' => $url]);
        }

        if ($provider === 'azure') {
            $url = Socialite::driver('azure')
                ->scopes(['Mail.Send', 'offline_access'])
                ->with(['state' => $state])
                ->stateless()
                ->redirect()
                ->getTargetUrl();
            return response()->json(['redirect_url' => $url]);
        }

        abort(404);
    }

    public function redirect(string $provider)
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS)) {
            abort(404);
        }

        if ($provider === 'google') {
            return Socialite::driver('google')
                ->scopes(['https://www.googleapis.com/auth/gmail.send'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->stateless()->redirect();
        }

        if ($provider === 'azure') {
            return Socialite::driver('azure')
                ->scopes(['Mail.Send', 'offline_access'])
                ->stateless()->redirect();
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider)
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS)) {
            abort(404);
        }

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/login?error=oauth_failed');
        }

        // Mode "link" : lier un compte OAuth à un user déjà authentifié
        $rawState = request()->get('state');
        if ($rawState) {
            $decoded = json_decode(base64_decode($rawState), true);
            if (
                is_array($decoded) &&
                ($decoded['mode'] ?? '') === 'link' &&
                isset($decoded['uid'], $decoded['exp'], $decoded['sig']) &&
                $decoded['exp'] > now()->timestamp
            ) {
                $expectedSig = hash_hmac('sha256', "{$decoded['uid']}.{$decoded['exp']}", config('app.key'));
                if (hash_equals($expectedSig, $decoded['sig'])) {
                    return $this->handleLinkCallback($provider, $socialUser, (int) $decoded['uid'], $frontendUrl);
                }
            }
        }

        // 1. SocialAccount existant → récupérer l'user associé
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;
            $updateData = ['token' => $socialUser->token, 'avatar' => $socialUser->getAvatar()];
            if ($socialUser->refreshToken) {
                $updateData['refresh_token'] = $socialUser->refreshToken;
            }
            $socialAccount->update($updateData);
        } else {
            // 2. Normaliser l'email
            $email = strtolower(trim($socialUser->getEmail()));

            // 3. Invitation en attente pour cet email (avant création de l'user)
            $invitation = TeamInvitation::pending()
                ->where('email', $email)
                ->with('team')
                ->first();

            // 4. firstOrCreate évite la race condition OAuth (deux callbacks simultanés)
            $name  = $socialUser->getName() ?? $email;
            $parts = explode(' ', $name, 2);
            $user  = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'      => $parts[1] ?? $parts[0],
                    'firstname' => $parts[0],
                    'password'  => Hash::make(Str::random(32)),
                ]
            );

            // 5. Attacher SocialAccount si pas encore lié pour ce provider
            if (!$user->socialAccounts()->where('provider', $provider)->exists()) {
                $user->socialAccounts()->create([
                    'provider'      => $provider,
                    'provider_id'   => $socialUser->getId(),
                    'avatar'        => $socialUser->getAvatar(),
                    'token'         => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                ]);
            }

            if ($invitation) {
                // Accepter l'invitation
                $invitation->team->users()->attach($user, ['role' => $invitation->role]);
                $invitation->update(['accepted_at' => now()]);
            }
            // Pas d'invitation → l'user crée son cabinet via l'onboarding
        }

        // Delete any existing OAuth tokens to prevent accumulation
        $user->tokens()->where('name', 'auth_token')->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Use URL fragment (#) instead of query string so the token is never sent
        // to the server in the Referer header and never appears in server access logs.
        return redirect($frontendUrl . '/auth/callback#token=' . $token);
    }

    /**
     * Gère le callback en mode "link" : attache ou met à jour le SocialAccount
     * du user identifié par l'état signé, sans créer de nouvelle session.
     */
    private function handleLinkCallback(string $provider, $socialUser, int $userId, string $frontendUrl): \Illuminate\Http\RedirectResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return redirect($frontendUrl . '/settings/cabinet?oauth=error&reason=user_not_found');
        }

        $user->socialAccounts()->updateOrCreate(
            ['provider' => $provider],
            [
                'provider_id'   => $socialUser->getId(),
                'avatar'        => $socialUser->getAvatar(),
                'token'         => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]
        );

        return redirect($frontendUrl . '/settings/cabinet?oauth=linked&provider=' . $provider);
    }

}
