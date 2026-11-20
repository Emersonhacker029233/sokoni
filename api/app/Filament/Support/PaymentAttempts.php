<?php

namespace App\Filament\Support;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The mobile-money attempts behind a Bolt delivery fee or a return ride
 * fee, for the admin pages: each attempt with its provider, Dalipay
 * references and status, every Dalipay webhook received for it, and the
 * "Mark payment refunded" action for money owed back to the buyer.
 *
 * The buyer's number is never shown (it is stored encrypted); the
 * channel and the last digits are enough to match a Dalipay statement.
 */
class PaymentAttempts
{
    /** @param  callable(Model): Collection<int, Payment>  $payments */
    public static function section(callable $payments): Section
    {
        return Section::make('Payments (mobile money)')
            ->schema([
                TextEntry::make('payment_attempts')
                    ->label('Attempts, newest first')
                    ->state(fn (Model $record) => self::attemptsText($payments($record)))
                    ->extraAttributes(['class' => 'whitespace-pre-line']),
                TextEntry::make('payment_webhooks')
                    ->label('Dalipay webhooks')
                    ->state(fn (Model $record) => self::webhooksText($payments($record)))
                    ->extraAttributes(['class' => 'whitespace-pre-line']),
            ]);
    }

    /** @param Collection<int, Payment> $payments */
    public static function attemptsText(Collection $payments): string
    {
        if ($payments->isEmpty()) {
            return 'None. Fees recorded by an admin show in the timeline.';
        }

        return $payments->sortByDesc('id')->map(function (Payment $p) {
            $meta = (array) $p->meta;
            $line = "#{$p->id} · {$p->provider} · ".($p->channel ?? '—').' · TSh '.number_format((int) $p->amount)
                .' · '.strtoupper($p->status)
                .($p->failure_reason ? " ({$p->failure_reason})" : '')
                .' · sent '.$p->created_at->format('d M H:i:s')
                .($p->paid_at ? ' · paid '.$p->paid_at->format('d M H:i:s') : '')
                .' · number ending '.substr((string) $p->msisdn, -3);
            $refs = array_filter([
                $p->provider_reference ? 'Dalipay uuid '.$p->provider_reference : ($p->provider === 'dalipay' ? 'no Dalipay uuid (no answer when sent)' : null),
                isset($meta['dalipay_reference']) ? 'reference '.$meta['dalipay_reference'] : null,
                $p->provider === 'dalipay' ? 'external_id '.($meta['external_id'] ?? '—') : null,
                isset($meta['dalipay_status']) ? 'Dalipay status '.$meta['dalipay_status'] : null,
            ]);
            $notes = array_filter([
                isset($meta['late_after']) ? "confirmed by the provider after it had failed here ({$meta['late_after']})" : null,
                $p->status === 'refund_pending' ? 'REFUND OWED to the buyer: the fee was already settled when this payment arrived' : null,
                $p->status === 'refunded' ? 'refunded'.($p->refunded_at ? ' '.$p->refunded_at->format('d M H:i') : '').(isset($meta['refund_reference']) ? ' · ref '.$meta['refund_reference'] : '') : null,
                isset($meta['amount_mismatch']) ? "AMOUNT MISMATCH: the provider confirmed TSh {$meta['amount_mismatch']}; not applied, check Dalipay" : null,
            ]);

            return $line.($refs ? "\n    ".implode(' · ', $refs) : '').($notes ? "\n    ".implode("\n    ", $notes) : '');
        })->implode("\n");
    }

    /** @param Collection<int, Payment> $payments */
    public static function webhooksText(Collection $payments): string
    {
        $uuids = $payments->where('provider', 'dalipay')->pluck('provider_reference')->filter()->values();
        if ($uuids->isEmpty()) {
            return '—';
        }

        $events = PaymentWebhookEvent::query()->where('provider', 'dalipay')
            ->where(fn ($q) => $uuids->each(fn (string $uuid) => $q->orWhere('event_id', 'like', $uuid.':%')))
            ->orderBy('id')
            ->get();

        return $events->isEmpty()
            ? 'None received (status checks cover it).'
            : $events->map(fn (PaymentWebhookEvent $e) => $e->created_at->format('d M H:i:s').' · '.($e->payload['event'] ?? '?')
                .' · '.($e->payload['data']['reference'] ?? $e->event_id)
                .' · '.($e->result ?? 'not processed'))->implode("\n");
    }

    /**
     * Money owed back to the buyer (a payment that arrived after the fee was
     * settled). Dalipay has no refund API: an admin pays it outside the app
     * and records it here with the reference.
     *
     * @param  callable(Model): Collection<int, Payment>  $payments
     */
    public static function markRefundedAction(callable $payments): Action
    {
        return Action::make('markPaymentRefunded')
            ->label('Mark payment refunded')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (Model $record) => Auth::user()?->isAdminRole() && $payments($record)->contains('status', 'refund_pending'))
            ->requiresConfirmation()
            ->modalDescription('Only after the money has been sent back to the buyer, outside the app.')
            ->schema([
                Select::make('payment_id')
                    ->label('Payment')
                    ->options(fn (Model $record) => $payments($record)->where('status', 'refund_pending')
                        ->mapWithKeys(fn (Payment $p) => [$p->id => "#{$p->id} · TSh ".number_format((int) $p->amount).' · '.($p->channel ?? '—').' ending '.substr((string) $p->msisdn, -3)]))
                    ->required(),
                TextInput::make('reference')->label('Refund reference (M-Pesa code, Dalipay payout reference)')->required()->maxLength(80),
            ])
            ->action(function (Model $record, array $data) use ($payments) {
                $payment = $payments($record)->firstWhere('id', (int) $data['payment_id']);
                $updated = $payment === null ? 0 : Payment::query()->whereKey($payment->id)->where('status', 'refund_pending')
                    ->update(['status' => 'refunded', 'refunded_at' => now(), 'meta' => json_encode([...(array) $payment->meta, 'refund_reference' => $data['reference']]), 'updated_at' => now()]);
                if ($updated !== 1) {
                    Notification::make()->title('That payment is no longer owed back')->danger()->send();

                    return;
                }
                ActivityLogger::record(Auth::user(), 'payment.refunded', $record, $data['reference'], ['payment_id' => $payment->id, 'amount' => (int) $payment->amount]);
                Notification::make()->title('Payment marked as refunded')->success()->send();
            });
    }
}
