<?php

namespace App\Providers;

use App\Services\DiarizationMonitoringService;
use App\Services\DiarizationService;
use App\Services\PyannoteHealthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AudioServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enregistrer les services comme singletons
        $this->app->singleton(PyannoteHealthService::class);
        $this->app->singleton(DiarizationMonitoringService::class);

        // Injecter le monitoring dans le service de diarisation
        $this->app->singleton(DiarizationService::class, function ($app) {
            return new DiarizationService(
                $app->make(DiarizationMonitoringService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Vérifier la santé de pyannote au démarrage (seulement en production ou si configuré)
        if ($this->shouldCheckHealthOnBoot()) {
            $this->checkPyannoteHealth();
        }
    }

    /**
     * Détermine si le health check doit être exécuté au démarrage
     */
    private function shouldCheckHealthOnBoot(): bool
    {
        // Désactiver pour les commandes artisan qui ne nécessitent pas le check
        if ($this->app->runningInConsole()) {
            $command = $_SERVER['argv'][1] ?? '';
            $excludedCommands = [
                'migrate',
                'db:seed',
                'cache:clear',
                'config:cache',
                'route:cache',
                'view:cache',
                'optimize',
                'package:discover',
            ];

            foreach ($excludedCommands as $excluded) {
                if (str_starts_with($command, $excluded)) {
                    return false;
                }
            }
        }

        // Activer si configuré explicitement ou en production
        return config('services.pyannote.check_on_boot', false)
            || $this->app->environment('production');
    }

    /**
     * Vérifie la santé de pyannote et log le résultat
     */
    private function checkPyannoteHealth(): void
    {
        try {
            $healthService = $this->app->make(PyannoteHealthService::class);
            $status = $healthService->check();

            if ($status['available']) {
                Log::info('[AUDIO PROVIDER] Pyannote disponible et fonctionnel');
            } else {
                Log::warning('[AUDIO PROVIDER] Pyannote non disponible - diarisation désactivée', [
                    'errors' => $status['errors'],
                    'warnings' => $status['warnings']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[AUDIO PROVIDER] Erreur lors de la vérification de pyannote', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
