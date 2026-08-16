<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Updates auto-expire 24h after posting (CLAUDE.md Part 3) — hourly is
// frequent enough that nothing visibly stale lingers, without adding load
// on every request the way computing "is this expired" per-read would.
// The cPanel cron entry (docs/DEPLOY.md) runs `schedule:run` every minute,
// which is what actually fires this.
Schedule::command('updates:delete-expired')->hourly();
