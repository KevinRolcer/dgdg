<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Honor reverse-proxy HTTPS headers in shared hosting / CDN setups.
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'agenda.access' => \App\Http\Middleware\AgendaAccess::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->job(new \App\Jobs\SendAgendaRemindersJob)->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        //
    })->create();

