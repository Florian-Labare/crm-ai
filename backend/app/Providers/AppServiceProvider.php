<?php

namespace App\Providers;

use App\Models\Client;
use App\Observers\ClientObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerObservers();
    }

    /**
     * Enregistre les observers des modèles
     */
    protected function registerObservers(): void
    {
        Client::observe(ClientObserver::class);
    }

    /**
     * Configure rate limiting pour les différents endpoints
     */
    protected function configureRateLimiting(): void
    {
        // Rate limiting pour l'upload audio : 10 uploads par minute par utilisateur
        RateLimiter::for('audio-upload', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de requêtes d\'upload. Veuillez patienter avant de réessayer.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        // Rate limiting pour les chunks : 30 chunks par minute par session
        RateLimiter::for('audio-chunk', function (Request $request) {
            $sessionId = $request->input('session_id', 'unknown');
            return Limit::perMinute(30)
                ->by($request->user()?->id . ':' . $sessionId)
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de chunks envoyés. Veuillez patienter.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        // Rate limiting pour la finalisation : 5 par minute par utilisateur
        RateLimiter::for('audio-finalize', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de finalisations. Veuillez patienter.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        // Rate limiting pour les corrections de speakers : 20 par minute
        RateLimiter::for('speaker-correction', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting strict pour le health check (éviter abus) : 30 par minute
        RateLimiter::for('health-check', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
