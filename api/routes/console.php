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

// C2 (tester feedback): purges a soft-deleted product's media files/rows
// once its 30-day recovery window has passed — see the command's own
// docblock. Daily is plenty; this is a storage-cost cleanup, not
// something anyone is ever waiting on in real time.
Schedule::command('products:purge-deleted-media')->daily();

// C6: sends one throttled batch of a pending bulk-SMS blast's recipients
// per tick — see ProcessSmsBlasts's own docblock for why this can't run
// inline from the Filament action that creates the blast.
Schedule::command('sms:process-blasts')->everyMinute()->withoutOverlapping();

// Delivery by Bolt (docs/delivery/BOLT.md). Registered only once Bolt is
// switched on or its credentials are in .env, so a server without Bolt
// (production today) schedules exactly what it did before.
//  - delivery:tick, every minute: payments, the unpaid-fee timeout, Bolt
//    quote/booking retries with backoff, Bolt receipts.
//  - delivery:track, every 10 s (Bolt's fair-use limit for ride details):
//    running rides. Sub-minute tasks keep `schedule:run` alive for the
//    whole minute, which is why this one is never registered without Bolt.
if (config('delivery.enabled') || filled(config('delivery.bolt.client_id'))) {
    Schedule::command('delivery:tick')->everyMinute()->withoutOverlapping(5);
    Schedule::command('delivery:track')->everyTenSeconds()->withoutOverlapping(1);
}

// Dalipay payments (docs/delivery/BOLT.md): open and recently timed-out
// attempts are checked with Dalipay every minute, so a lost webhook never
// strands a paid fee. Registered whenever Dalipay is the provider or its
// keys are in .env, independent of the Bolt flags; nothing otherwise.
if (config('payments.provider') === 'dalipay' || filled(config('payments.dalipay.public_key')) || filled(config('payments.dalipay.secret_key'))) {
    Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping(5);
}

// Apple Guideline 1.2: a daily email to MODERATION_ALERT_EMAIL listing every
// report still pending after 20 hours, before it breaks the 24-hour promise.
// 05:00 UTC is 08:00 in Dar es Salaam. Sends nothing on a clear queue.
Schedule::command('reports:waiting-summary')->dailyAt('05:00');
