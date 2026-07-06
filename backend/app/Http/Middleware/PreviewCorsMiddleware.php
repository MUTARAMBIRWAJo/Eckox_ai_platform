<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreviewCorsMiddleware
{
    /**
     * Handle an incoming request.
     * Validate Vercel Preview Deployments dynamically without exposing wildcard origins.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin) {
            // Regex to match eckox-<hash>-mutarambirwaj1-gmailcoms-projects.vercel.app preview structures securely
            $pattern = '/^https:\/\/eckox-[a-z0-9\-]+-mutarambirwaj1-gmailcoms-projects\.vercel\.app$/';

            if (preg_match($pattern, $origin)) {
                $response = $next($request);

                // Add CORS headers dynamically
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Trace-ID');

                // If preflight request, immediately return 200 OK
                if ($request->isMethod('OPTIONS')) {
                    $response->setStatusCode(200);
                }

                return $response;
            }
        }

        return $next($request);
    }
}
