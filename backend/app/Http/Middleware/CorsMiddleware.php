<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'http://localhost:5173',           // ✅ Dev (Vite)
            'https://ton-domaine.fr',          // ✅ Prod
            'https://app.ton-domaine.fr',      // ✅ Sous-domaine prod
        ];

        $origin = $request->headers->get('Origin');
        $response = $request->getMethod() === 'OPTIONS'
            ? response('', 200)
            : $next($request);

        // On autorise seulement les origines connues
        if (in_array($origin, $allowedOrigins, true)) {
            $this->addCorsHeaders($response, $origin);
        }

        return $response;
    }

    /**
     * Ajoute les en-têtes CORS à la réponse.
     */
    private function addCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}
