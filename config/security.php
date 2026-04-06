<?php

$csv = static function (string $value): array {
    return array_values(array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode(',', $value)
    )));
};

$isLocal = env('APP_ENV', 'production') === 'local';

$defaultCorsOrigins = $isLocal
    ? 'http://localhost,http://127.0.0.1,http://segob.test'
    : '';

$defaultScriptSrc = "https://cdnjs.cloudflare.com,https://cdn.jsdelivr.net,https://unpkg.com,https://static.cloudflareinsights.com";
$defaultStyleSrc = "https://fonts.googleapis.com,https://cdnjs.cloudflare.com,https://cdn.jsdelivr.net";
$defaultImgSrc = "https://*.openstreetmap.org";
$defaultFontSrc = "https://fonts.gstatic.com,https://cdnjs.cloudflare.com,https://cdn.jsdelivr.net";
$defaultConnectSrc = "https://static.cloudflareinsights.com,https://cdn.jsdelivr.net,https://cdnjs.cloudflare.com,https://unpkg.com,https://nominatim.openstreetmap.org";

return [
    'trusted_cors_origins' => $csv((string) env('SECURITY_TRUSTED_CORS_ORIGINS', $defaultCorsOrigins)),
    'php_include_path_prefix' => trim((string) env('PHP_INCLUDE_PATH_PREFIX', '')),
    'csp' => [
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
        'report_uri' => trim((string) env('SECURITY_CSP_REPORT_URI', '')),
        'allow_unsafe_inline_scripts' => (bool) env('SECURITY_CSP_ALLOW_UNSAFE_INLINE_SCRIPTS', true),
        'allow_unsafe_eval_scripts' => (bool) env('SECURITY_CSP_ALLOW_UNSAFE_EVAL_SCRIPTS', true),
        'script_src' => $csv((string) env('SECURITY_CSP_SCRIPT_SRC', $defaultScriptSrc)),
        'style_src' => $csv((string) env('SECURITY_CSP_STYLE_SRC', $defaultStyleSrc)),
        'img_src' => $csv((string) env('SECURITY_CSP_IMG_SRC', $defaultImgSrc)),
        'font_src' => $csv((string) env('SECURITY_CSP_FONT_SRC', $defaultFontSrc)),
        'connect_src' => $csv((string) env('SECURITY_CSP_CONNECT_SRC', $defaultConnectSrc)),
    ],
];
