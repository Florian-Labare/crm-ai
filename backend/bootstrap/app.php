<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CorsMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->trustHosts();

        // ✅ Ajoute ton middleware CORS à la pile globale
        $middleware->append(CorsMiddleware::class);
        // (ou $middleware->prepend(CorsMiddleware::class) si tu veux le tout premier)
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
