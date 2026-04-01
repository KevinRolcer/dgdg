<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // 301 en POST suele convertirse en GET en el navegador y se pierde el cuerpo (CSRF, format=pdf, cfg).
        // 308 preserva método y cuerpo; en GET/HEAD seguimos con 301 por caché/SEO.
        $redirectPermanent = in_array($request->method(), ['GET', 'HEAD'], true) ? 301 : 308;

        if ($this->mustRedirectToCanonicalHost($request)) {
            return redirect()->to($this->buildCanonicalUrl($request), $redirectPermanent);
        }

        if ($this->mustRedirectToHttps($request)) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), $redirectPermanent);
        }

        /** @var Response $response */
        $response = $next($request);

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType === '' || str_contains($contentType, 'text/html')) {
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $cspHeader = (bool) config('security.csp.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        $response->headers->set($cspHeader, $this->buildContentSecurityPolicy($request));

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        // Avoid stale CSRF tokens from browser/CDN cache on the login page.
        if ($request->isMethod('GET') && $request->routeIs('login')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        $host = (string) $request->getHost();
        $isLoopbackHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if ($request->isSecure() || $isLoopbackHost) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        } else {
            $response->headers->remove('Cross-Origin-Opener-Policy');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function mustRedirectToHttps(Request $request): bool
    {
        if (app()->isLocal() || ! config('app.force_https', false)) {
            return false;
        }

        if ($request->isSecure()) {
            return false;
        }

        $forwardedProto = strtolower((string) $request->header('x-forwarded-proto', ''));
        if (str_contains($forwardedProto, 'https')) {
            return false;
        }

        return true;
    }

    private function mustRedirectToCanonicalHost(Request $request): bool
    {
        if ($request->isMethod('OPTIONS') || $request->headers->has('Access-Control-Request-Method')) {
            return false;
        }

        if (app()->isLocal()) {
            return false;
        }

        $canonicalHost = $this->getConfiguredHost();
        if ($canonicalHost === null) {
            return false;
        }

        return strcasecmp((string) $request->getHost(), $canonicalHost) !== 0;
    }

    private function buildCanonicalUrl(Request $request): string
    {
        $canonicalHost = $this->getConfiguredHost() ?? (string) $request->getHost();
        $scheme = $this->resolvePreferredScheme($request);

        return $scheme.'://'.$canonicalHost.$request->getRequestUri();
    }

    private function resolvePreferredScheme(Request $request): string
    {
        if (config('app.force_https', false)) {
            return 'https';
        }

        if ($request->isSecure()) {
            return 'https';
        }

        $forwardedProto = strtolower((string) $request->header('x-forwarded-proto', ''));
        if (str_contains($forwardedProto, 'https')) {
            return 'https';
        }

        return 'http';
    }

    private function getConfiguredHost(): ?string
    {
        $appUrl = (string) config('app.url', '');
        if ($appUrl === '') {
            return null;
        }

        $host = parse_url($appUrl, PHP_URL_HOST);
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return strtolower($host);
    }

    private function buildContentSecurityPolicy(Request $request): string
    {
        $isLocal = app()->isLocal();
        $csp = (array) config('security.csp', []);
        $scriptSrc = $this->buildSourceList(
            ["'self'"],
            (array) ($csp['script_src'] ?? []),
            [
                'allowUnsafeInline' => (bool) ($csp['allow_unsafe_inline_scripts'] ?? true),
                'allowUnsafeEval' => (bool) ($csp['allow_unsafe_eval_scripts'] ?? $isLocal),
            ]
        );
        $styleSrc = $this->buildSourceList(
            ["'self'"],
            (array) ($csp['style_src'] ?? []),
            ['allowUnsafeInline' => true]
        );
        $imgSrc = $this->buildSourceList(
            ["'self'", 'data:', 'blob:', 'https:'],
            (array) ($csp['img_src'] ?? []),
            ['allowHttpScheme' => $isLocal]
        );
        $mediaSrc = $this->buildSourceList(
            ["'self'", 'data:', 'blob:', 'https:'],
            [],
            ['allowHttpScheme' => $isLocal]
        );
        $fontSrc = $this->buildSourceList(
            ["'self'", 'data:'],
            (array) ($csp['font_src'] ?? []),
        );
        $connectSrc = $this->buildSourceList(
            ["'self'", 'https:'],
            (array) ($csp['connect_src'] ?? []),
            ['allowHttpScheme' => $isLocal]
        );

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            'script-src '.$scriptSrc,
            'style-src '.$styleSrc,
            'img-src '.$imgSrc,
            'media-src '.$mediaSrc,
            'font-src '.$fontSrc,
            'connect-src '.$connectSrc,
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $reportUri = trim((string) ($csp['report_uri'] ?? ''));
        if ($reportUri !== '') {
            $directives[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $directives);
    }

    /**
     * @param  array<int, string>  $baseSources
     * @param  array<int, string>  $configuredSources
     * @param  array<string, bool>  $options
     */
    private function buildSourceList(array $baseSources, array $configuredSources, array $options = []): string
    {
        $sources = $baseSources;

        if (($options['allowUnsafeInline'] ?? false) === true) {
            $sources[] = "'unsafe-inline'";
        }

        if (($options['allowUnsafeEval'] ?? false) === true) {
            $sources[] = "'unsafe-eval'";
        }

        if (($options['allowHttpScheme'] ?? false) === true) {
            $sources[] = 'http:';
        }

        foreach ($configuredSources as $source) {
            $source = trim((string) $source);
            if ($source !== '') {
                $sources[] = $source;
            }
        }

        $unique = array_values(array_unique($sources));

        return implode(' ', $unique);
    }
}
