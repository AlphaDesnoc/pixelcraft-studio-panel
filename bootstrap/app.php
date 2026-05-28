<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'project.member' => \App\Http\Middleware\EnsureProjectMember::class,
            'project.feature' => \App\Http\Middleware\EnsureProjectFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function ($schedule): void {
        $schedule->command('panel:due-reminders')->dailyAt('08:00');
        $schedule->command('panel:recurring-tasks')->dailyAt('07:00');
        $schedule->command('panel:auto-archive-tasks')->dailyAt('03:00');
        $schedule->command('calendar:send-reminders')->everyMinute();
        $schedule->command('bugs:notify-sla')->everyFifteenMinutes();
        $schedule->command('panel:weekly-digest')->weeklyOn(1, '08:30');
        $schedule->command('panel:daily-digest')->dailyAt('08:00');
        $schedule->command('panel:task-reminders')->everyMinute();
    })->create();
