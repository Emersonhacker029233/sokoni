<?php

namespace App\Console\Commands;

use App\Models\SmsBlast;
use App\Services\Sms\SmsGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * C6: the admin bulk-SMS tool's actual sender. This host has no queue
 * worker running (`QUEUE_CONNECTION=database` with nothing consuming it —
 * see docs/DEPLOY.md), so a blast is never sent inline from the Filament
 * action that creates it — that would either time out an HTTP request on a
 * large audience or hammer the SMS provider all at once. Instead the
 * Filament page only snapshots the recipient list; this command, fired
 * every minute by the cron already running `schedule:run` (routes/console.php),
 * sends one bounded batch per tick until every blast is done — the same
 * "no worker, but cron exists" pattern this project already relies on for
 * `updates:delete-expired`.
 */
class ProcessSmsBlasts extends Command
{
    protected $signature = 'sms:process-blasts';

    protected $description = 'Send the next batch of pending bulk-SMS blast recipients (throttled, one tick at a time).';

    public function handle(SmsGateway $gateway): int
    {
        $batchSize = (int) config('sokoni.sms_blast_batch_size', 20);

        // One blast at a time, oldest first — a second blast queued while
        // the first is still sending simply waits its turn rather than
        // interleaving with it.
        $blast = SmsBlast::query()->whereIn('status', ['pending', 'processing'])->oldest()->first();

        if (! $blast) {
            return self::SUCCESS;
        }

        if ($blast->status === 'pending') {
            $blast->forceFill(['status' => 'processing'])->save();
        }

        $recipients = $blast->recipients()->where('status', 'pending')->limit($batchSize)->get();

        foreach ($recipients as $recipient) {
            try {
                $gateway->sendMessage($recipient->phone, $blast->message);
                $recipient->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
                $blast->increment('sent_count');
            } catch (\Throwable $e) {
                $recipient->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
                $blast->increment('failed_count');
                Log::channel('sms')->error('Bulk SMS: recipient failed', [
                    'sms_blast_id' => $blast->id,
                    'user_id' => $recipient->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($blast->fresh()->isDone()) {
            $blast->forceFill(['status' => 'completed'])->save();
            Log::channel('sms')->info('Bulk SMS: blast completed', [
                'sms_blast_id' => $blast->id,
                'sent' => $blast->sent_count,
                'failed' => $blast->failed_count,
            ]);
        }

        return self::SUCCESS;
    }
}
