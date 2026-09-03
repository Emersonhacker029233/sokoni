<?php

namespace Tests\Feature;

use App\Models\SmsBlast;
use App\Models\SmsBlastRecipient;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/** C6: the cron-driven sender behind the bulk-SMS tool — see ProcessSmsBlasts's own docblock for why this exists instead of sending inline. */
class ProcessSmsBlastsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBlast(int $recipientCount): SmsBlast
    {
        $blast = SmsBlast::forceCreate([
            'sender_id' => User::factory()->admin()->create()->id,
            'audience' => 'all',
            'consent_only' => false,
            'message' => 'Hello from Sokoni',
            'total_count' => $recipientCount,
        ]);

        for ($i = 0; $i < $recipientCount; $i++) {
            $user = User::factory()->create();
            SmsBlastRecipient::forceCreate([
                'sms_blast_id' => $blast->id,
                'user_id' => $user->id,
                'phone' => $user->phone,
            ]);
        }

        return $blast;
    }

    public function test_one_tick_sends_only_up_to_the_configured_batch_size(): void
    {
        Config::set('sokoni.sms_blast_batch_size', 2);
        $blast = $this->makeBlast(5);

        $this->artisan('sms:process-blasts')->assertSuccessful();

        $blast->refresh();
        $this->assertSame(2, $blast->sent_count);
        $this->assertSame('processing', $blast->status);
        $this->assertSame(3, $blast->recipients()->where('status', 'pending')->count());
    }

    public function test_repeated_ticks_eventually_complete_the_whole_blast(): void
    {
        Config::set('sokoni.sms_blast_batch_size', 2);
        $blast = $this->makeBlast(5);

        $this->artisan('sms:process-blasts');
        $this->artisan('sms:process-blasts');
        $this->artisan('sms:process-blasts');

        $blast->refresh();
        $this->assertSame(5, $blast->sent_count);
        $this->assertSame(0, $blast->failed_count);
        $this->assertSame('completed', $blast->status);
    }

    public function test_a_gateway_failure_marks_that_recipient_failed_with_the_error_and_does_not_stop_the_batch(): void
    {
        $blast = $this->makeBlast(2);
        $failingPhone = $blast->recipients()->first()->phone;

        $fake = new class($failingPhone) implements SmsGateway
        {
            public function __construct(private readonly string $failingPhone) {}

            public function sendOtp(string $phone, string $code, string $locale = 'en'): void {}

            public function sendMessage(string $phone, string $body): void
            {
                if ($phone === $this->failingPhone) {
                    throw new \RuntimeException('carrier rejected');
                }
            }
        };
        $this->app->instance(SmsGateway::class, $fake);

        $this->artisan('sms:process-blasts');

        $blast->refresh();
        $this->assertSame(1, $blast->sent_count);
        $this->assertSame(1, $blast->failed_count);
        $this->assertSame('completed', $blast->status);
        $this->assertDatabaseHas('sms_blast_recipients', [
            'sms_blast_id' => $blast->id,
            'phone' => $failingPhone,
            'status' => 'failed',
            'error' => 'carrier rejected',
        ]);
    }

    public function test_the_oldest_pending_blast_is_processed_before_a_newer_one(): void
    {
        Config::set('sokoni.sms_blast_batch_size', 10);
        $older = $this->makeBlast(1);
        $older->forceFill(['created_at' => now()->subHour()])->save();
        $newer = $this->makeBlast(1);

        $this->artisan('sms:process-blasts');

        $this->assertSame('completed', $older->fresh()->status);
        $this->assertSame('pending', $newer->fresh()->status);
    }

    public function test_a_tick_with_nothing_pending_does_nothing_and_succeeds(): void
    {
        $this->artisan('sms:process-blasts')->assertSuccessful();
        $this->assertSame(0, SmsBlast::count());
    }
}
