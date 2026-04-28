<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks (RGPD & Maintenance)
|--------------------------------------------------------------------------
|
| Ces tâches s'exécutent automatiquement via le cron scheduler.
| Assurez-vous d'avoir configuré le cron sur le serveur :
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Nettoyage des fichiers temporaires orphelins - tous les jours à 3h du matin
Schedule::command('audio:cleanup-temp --hours=24')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cleanup-temp.log'))
    ->description('Nettoie les fichiers temporaires orphelins');

// Purge des anciens fichiers audio (RGPD - rétention 30 jours) - tous les jours à 4h
Schedule::command('audio:purge-old --days=30')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purge-audio.log'))
    ->description('Supprime les fichiers audio de plus de 30 jours (RGPD)');

