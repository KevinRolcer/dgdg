<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$includePathPrefix = trim((string) env('PHP_INCLUDE_PATH_PREFIX', ''));
if ($includePathPrefix !== '') {
    ini_set('include_path', $includePathPrefix.PATH_SEPARATOR.ini_get('include_path'));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Honor reverse-proxy HTTPS headers in shared hosting / CDN setups.
        $middleware->trustProxies(at: '*');

        // Handle API CORS before security headers (so OPTIONS requests are handled first)
        $middleware->append(\App\Http\Middleware\HandleApiCors::class);

        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'agenda.access' => \App\Http\Middleware\AgendaAccess::class,
            'agenda.access.escritura' => \App\Http\Middleware\AgendaAccessEscritura::class,
            'whatsapp.nostore' => \App\Http\Middleware\WhatsAppNoStoreResponse::class,
            'whatsapp.sensitive.confirm' => \App\Http\Middleware\ConfirmWhatsAppSensitiveAccess::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->job(new \App\Jobs\SendAgendaRemindersJob)->everyMinute();
        $schedule->command('whatsapp-chats:prune')->dailyAt('03:15');
        $schedule->command('whatsapp-chats:backup')->weeklyOn(1, '04:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        //
    })->create();
