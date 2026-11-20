<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

use App\Filament\Support\LivePosition;
use App\Filament\Support\PaymentAttempts;
use App\Models\ActivityLog;
use App\Models\ReturnRequest;
use App\Support\PrivateFileUrl;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReturnRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Return')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order.code')->label('Order'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('reason')->formatStateUsing(fn (string $state) => __("returns.reasons.{$state}", [], 'en')),
                        TextEntry::make('buyer.name')->label('Buyer'),
                        TextEntry::make('shop.shop_name')->label('Shop'),
                        TextEntry::make('order.return_policy')->label('Policy at checkout'),
                        TextEntry::make('created_at')->label('Requested')->dateTime(),
                        TextEntry::make('seller_deadline')->label('Shop had until')
                            ->state(fn (ReturnRequest $record) => $record->sellerDeadline())->dateTime(),
                        TextEntry::make('escalation_reason')->label('Escalated because')->placeholder('—'),
                    ]),

                Section::make("Buyer's comment and photos")
                    ->description(fn (ReturnRequest $record) => $record->is_hidden ? 'Hidden from the shop by a moderator.' : null)
                    ->schema([
                        TextEntry::make('comment')->placeholder('No comment')->columnSpanFull(),
                        ImageEntry::make('photo_urls')
                            ->label('Photos')
                            ->height(180)
                            ->placeholder('No photos')
                            ->state(fn (ReturnRequest $record) => $record->photos->map(fn ($p) => PrivateFileUrl::for($p->path))->filter()->values()->all()),
                    ]),

                Section::make('Decisions')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('decided_role')->label('Decided by')->placeholder('Nobody yet'),
                        TextEntry::make('decided_at')->dateTime()->placeholder('—'),
                        TextEntry::make('decision_note')->label("Shop's note / reason")->placeholder('—')->columnSpanFull(),
                        TextEntry::make('dispute_comment')->label("Buyer's dispute")->placeholder('—')->columnSpanFull(),
                        TextEntry::make('resolution_note')->label('Sokoni decision')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Bolt ride back (customer to shop)')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ride_status')->label('Ride')->placeholder('Not booked')->badge(),
                        TextEntry::make('provider_ref')->label('Bolt ride ID')->placeholder('—')->copyable(),
                        TextEntry::make('provider_state')->label('Bolt state')->placeholder('—'),
                        TextEntry::make('driver_line')
                            ->label('Driver')
                            ->state(fn (ReturnRequest $record) => collect([
                                $record->provider_driver['name'] ?? null,
                                $record->provider_driver['phone'] ?? null,
                                $record->provider_driver['plate'] ?? null,
                                $record->provider_driver['model'] ?? null,
                            ])->filter()->implode(' · ') ?: '—'),
                        TextEntry::make('tracking_url')
                            ->label('Bolt tracking link')
                            ->state(fn (ReturnRequest $record) => $record->isRideActive() ? ($record->provider_driver['tracking_url'] ?? null) : null)
                            ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                            ->placeholder('Only while the ride runs'),
                        LivePosition::entry('live_position', fn (ReturnRequest $record) => $record->isRideActive()
                            && in_array($record->ride_status, ['driver_assigned', 'at_pickup', 'on_the_way'], true) ? (string) $record->provider_ref : null),
                    ]),

                Section::make('Ride back: estimate and cost')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payer')->label('Paid by')->badge(),
                        TextEntry::make('estimate_range')
                            ->label("Bolt's estimate when accepted")
                            ->state(fn (ReturnRequest $record) => $record->estimate_max === null ? '—'
                                : 'TSh '.number_format((float) $record->estimate_min).' – '.number_format((float) $record->estimate_max)),
                        TextEntry::make('fee')->label('Fee owed (upper bound)')->money('TZS', divideBy: 1)->placeholder('Not priced yet'),
                        TextEntry::make('final_cost')->label('Bolt receipt')->money('TZS', divideBy: 1)->placeholder('Not received yet'),
                        TextEntry::make('cost_difference')->label('Difference (fee − receipt)')->money('TZS', divideBy: 1)->placeholder('—'),
                        TextEntry::make('invoice')
                            ->label('Bolt invoice')
                            ->state(fn (ReturnRequest $record) => $record->receipt['invoice_url'] ?? null)
                            ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                    ]),

                Section::make('Refunds')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('goods_refund')
                            ->label('Goods: refunded by the shop')
                            ->badge()
                            ->state(fn (ReturnRequest $record) => match (true) {
                                $record->refunded_at !== null => 'Confirmed by the shop '.$record->refunded_at->format('d M H:i'),
                                $record->status === 'closed' => 'No refund: return closed',
                                $record->received_at !== null => 'Item back; waiting for the shop to refund',
                                default => 'Not yet',
                            })
                            ->color(fn (ReturnRequest $record) => $record->refunded_at !== null ? 'success' : ($record->received_at !== null ? 'warning' : 'gray')),
                        TextEntry::make('ride_fee_refund')
                            ->label('Ride fee')
                            ->badge()
                            ->state(fn (ReturnRequest $record) => match ($record->fee_status) {
                                'refund_pending' => 'Owed back to the buyer: the ride never carried the item',
                                'refunded' => 'Paid back to the buyer',
                                'paid' => 'Paid by the buyer',
                                'owed_by_seller' => 'Owed by the shop to Sokoni',
                                'unpaid' => 'Not paid yet',
                                'waived' => 'Waived',
                                default => '—',
                            })
                            ->color(fn (ReturnRequest $record) => match ($record->fee_status) {
                                'refund_pending' => 'danger',
                                'refunded', 'paid' => 'success',
                                default => 'gray',
                            }),
                    ]),

                PaymentAttempts::section(fn (ReturnRequest $record) => $record->payments()->get()),

                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('timeline_display')
                            ->label('')
                            ->state(fn (ReturnRequest $record) => self::timeline($record))
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ]);
    }

    /**
     * Every step of the return, oldest first: its own timestamps, plus what
     * admins did to it (activity log, with who and why).
     */
    public static function timeline(ReturnRequest $record): string
    {
        $who = fn (?string $role) => $role === 'admin' ? 'Sokoni' : 'the shop';
        $steps = collect([
            [$record->created_at, 'Requested by the buyer: '.__("returns.reasons.{$record->reason}", [], 'en')],
            [$record->escalated_at, 'Passed to Sokoni ('.str_replace('_', ' ', (string) $record->escalation_reason).')'],
            [$record->decided_at, 'Decided by '.$who($record->decided_role).($record->decision_note ? ': '.$record->decision_note : '')],
            [$record->disputed_at, 'Disputed by the buyer'.($record->dispute_comment ? ': '.$record->dispute_comment : '')],
            [$record->paid_at, 'Ride fee paid by the buyer'],
            [$record->booked_at, 'Bolt ride back booked'.($record->provider_ref ? ' (ride '.$record->provider_ref.')' : '')],
            [$record->picked_up_at, 'Bolt driver collected the item'],
            [$record->received_at, 'Item back at the shop'],
            [$record->refunded_at, 'Shop confirmed the refund'],
            [$record->closed_at, 'Closed ('.($record->closed_reason === 'withdrawn' ? 'withdrawn by the buyer' : 'by Sokoni').')'],
        ])->filter(fn (array $step) => $step[0] !== null);

        $admin = ActivityLog::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->with('causer')
            ->get()
            ->map(fn (ActivityLog $log) => [$log->created_at, 'Admin '.($log->causer?->name ?? '#'.$log->causer_id).': '.$log->action.($log->reason ? ' · '.$log->reason : '')]);

        return $steps->concat($admin)
            ->sortBy(fn (array $step) => $step[0]->getTimestamp())
            ->map(fn (array $step) => $step[0]->format('d M H:i').' · '.$step[1])
            ->implode("\n");
    }
}
