<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleApiCors
{
    /**
     * Handle an incoming request for CORS.
     *
     * Handles preflight OPTIONS requests for API endpoints that need CORS support.
     * This is particularly important for XHR/Fetch requests from the browser.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight requests (OPTIONS)
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $origin = (string) $request->header('Origin', '');
        $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));
        $requestHost = strtolower((string) $request->getHost());

        $allowedHosts = [
            'segob.test',
            'localhost',
            '127.0.0.1',
            '::1',
        ];

        $isTrustedOrigin = $origin !== ''
            && $originHost !== ''
            && (in_array($originHost, $allowedHosts, true) || $originHost === $requestHost);

        // Allow if same-origin request (no Origin header) or from trusted hosts.
        if ($origin === '' || $isTrustedOrigin) {
            $allowOrigin = $origin !== '' ? $origin : $request->getSchemeAndHttpHost();
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, HEAD, PATCH');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-Token');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->headers->set('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
        }

        return $response;
    }
}
