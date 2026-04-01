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

        $origin = $this->normalizeOrigin((string) $request->header('Origin', '')) ?? '';
        $sameOrigin = $this->normalizeOrigin($request->getSchemeAndHttpHost()) ?? $request->getSchemeAndHttpHost();

        $trustedOrigins = array_map(
            fn (string $candidate): ?string => $this->normalizeOrigin($candidate),
            (array) config('security.trusted_cors_origins', [])
        );
        $trustedOrigins = array_values(array_filter($trustedOrigins));

        $isTrustedOrigin = $origin !== ''
            && ($origin === $sameOrigin || in_array($origin, $trustedOrigins, true));

        // Allow if same-origin request (no Origin header) or from trusted configured origins.
        if ($origin === '' || $isTrustedOrigin) {
            $allowOrigin = $origin !== '' ? $origin : $sameOrigin;
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, HEAD, PATCH');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-Token');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->headers->set('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
        }

        return $response;
    }

    private function normalizeOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return null;
        }

        $parts = parse_url($origin);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }
}
