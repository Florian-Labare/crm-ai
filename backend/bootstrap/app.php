<?php

use App\Console\Commands\DeduplicateClients;
use App\Http\Middleware\CorsMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // ✅ CORS doit être en premier pour gérer les preflight et les erreurs
        $middleware->prepend(CorsMiddleware::class);
    })
    ->withCommands([
        DeduplicateClients::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        // API-only app : retourner 401 JSON au lieu de rediriger vers route('login')
        $exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            $allowedOrigins = config('cors.allowed_origins', ['http://localhost:5173']);
            $origin = $request->headers->get('Origin');
            if (in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }

            return $response;
        });
    })
    ->create();
