<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Runtime maintenance schedule (Aish POS shared-VPS pilot)
|--------------------------------------------------------------------------
| Database-backed queue/cache/session drivers accumulate rows that must be
| pruned. These tasks are safe: they never delete active/reserved jobs or
| sessions newer than the retention window. Times are staggered and every
| task uses withoutOverlapping() so a slow run never stacks.
*/

// Failed jobs: keep 14 days (336h) of history for triage.
Schedule::command('queue:prune-failed --hours=336')
    ->dailyAt('03:10')
    ->withoutOverlapping();

// Job batches: prune finished after 72h; unfinished/cancelled kept 7 days.
Schedule::command('queue:prune-batches --hours=72 --unfinished=168 --cancelled=168')
    ->dailyAt('03:20')
    ->withoutOverlapping();

// Expired sessions: retention clamps up to the session lifetime automatically.
Schedule::command('pilot:prune-sessions --hours=168 --apply')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Expired database cache rows and locks.
Schedule::command('pilot:prune-cache --apply')
    ->dailyAt('03:40')
    ->withoutOverlapping();
