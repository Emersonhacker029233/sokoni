<?php

namespace App\Filament\Pages;

use App\Models\SmsBlast;
use App\Models\SmsBlastRecipient;
use App\Services\Marketing\SmsAudienceResolver;
use App\Support\ActivityLogger;
use App\Support\Settings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * C6: promotional SMS to a selected audience (Marketing nav group). The
 * "Send" action here never sends anything itself — it snapshots the
 * matching recipients into `sms_blast_recipients` and hands off to
 * `ProcessSmsBlasts` (see its own docblock for why this must be
 * cron-driven, not inline), so this page's job is composing, previewing,
 * confirming and queuing, then showing progress on already-queued blasts.
 */
class BulkSms extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Bulk SMS';

    protected static ?string $slug = 'bulk-sms';

    protected string $view = 'filament.pages.bulk-sms';

    public string $audience = 'verified_sellers';

    public bool $consentOnly = false;

    public string $message = '';

    public bool $confirming = false;

    /** @var array<int, bool> */
    public array $showingFailures = [];

    public function getTitle(): string
    {
        return 'Bulk SMS';
    }

    public function recipientCount(): int
    {
        return SmsAudienceResolver::query($this->audience, $this->consentOnly)->count();
    }

    public function characterCount(): int
    {
        return mb_strlen($this->message);
    }

    /** GSM-7 single-segment length is 160 chars — a rough, honest estimate, not a billing-exact one. */
    public function messageUnits(): int
    {
        return $this->characterCount() === 0 ? 0 : (int) ceil($this->characterCount() / 160);
    }

    public function estimatedTotalUnits(): int
    {
        return $this->messageUnits() * $this->recipientCount();
    }

    public function maxBlastSize(): int
    {
        return Settings::maxSmsBlastSize();
    }

    public function updatedAudience(): void
    {
        $this->confirming = false;
    }

    public function updatedConsentOnly(): void
    {
        $this->confirming = false;
    }

    public function updatedMessage(): void
    {
        $this->confirming = false;
    }

    public function confirm(): void
    {
        if (trim($this->message) === '') {
            Notification::make()->title('Write a message first')->danger()->send();

            return;
        }

        $count = $this->recipientCount();

        if ($count === 0) {
            Notification::make()->title('No recipients match this audience')->danger()->send();

            return;
        }

        if ($count > $this->maxBlastSize()) {
            Notification::make()
                ->title("This audience has {$count} recipients — over the cap of {$this->maxBlastSize()} per blast")
                ->body('Narrow the audience (e.g. consenting users only) or split it into more than one send.')
                ->danger()
                ->send();

            return;
        }

        $this->confirming = true;
    }

    public function cancel(): void
    {
        $this->confirming = false;
    }

    public function send(): void
    {
        $recipients = SmsAudienceResolver::query($this->audience, $this->consentOnly)->get();

        // Re-checked at send time, not just at confirm time — the audience
        // is live data, and a few minutes can pass between the two clicks.
        if ($recipients->isEmpty() || $recipients->count() > $this->maxBlastSize()) {
            Notification::make()->title('Recipient count changed since you confirmed — please check and try again.')->danger()->send();
            $this->confirming = false;

            return;
        }

        $blast = DB::transaction(function () use ($recipients) {
            $blast = SmsBlast::forceCreate([
                'sender_id' => Auth::id(),
                'audience' => $this->audience,
                'consent_only' => $this->consentOnly,
                'message' => $this->message,
                'total_count' => $recipients->count(),
            ]);

            foreach ($recipients as $user) {
                SmsBlastRecipient::forceCreate([
                    'sms_blast_id' => $blast->id,
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                ]);
            }

            return $blast;
        });

        ActivityLogger::record(Auth::user(), 'marketing.sms_blast_queued', $blast, null, [
            'audience' => $this->audience,
            'consent_only' => $this->consentOnly,
            'recipient_count' => $blast->total_count,
        ]);

        $this->confirming = false;
        $this->message = '';

        Notification::make()
            ->title("Queued — sending to {$blast->total_count} recipients over the next few minutes.")
            ->success()
            ->send();
    }

    /** @return Collection<int, SmsBlast> */
    public function getRecentBlasts(): Collection
    {
        return SmsBlast::query()->with('sender')->latest()->limit(10)->get();
    }

    public function toggleFailures(int $blastId): void
    {
        $this->showingFailures[$blastId] = ! ($this->showingFailures[$blastId] ?? false);
    }

    /** @return Collection<int, SmsBlastRecipient> */
    public function failuresFor(int $blastId): Collection
    {
        return SmsBlastRecipient::query()
            ->where('sms_blast_id', $blastId)
            ->where('status', 'failed')
            ->with('user')
            ->get();
    }
}
