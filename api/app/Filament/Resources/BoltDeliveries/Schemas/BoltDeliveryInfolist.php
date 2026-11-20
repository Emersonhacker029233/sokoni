<?php

namespace App\Filament\Resources\BoltDeliveries\Schemas;

use App\Filament\Support\LivePosition;
use App\Filament\Support\PaymentAttempts;
use App\Models\DeliveryJob;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BoltDeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Delivery')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order.code')->label('Order'),
                        TextEntry::make('order.seller.shop_name')->label('Shop'),
                        TextEntry::make('order.buyer.name')->label('Buyer'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('failure_reason')->label('Problem')->placeholder('—'),
                        TextEntry::make('payment_status')->label('Fee')->badge(),
                        TextEntry::make('pickup_address')->label('Pickup (shop)')->placeholder('—'),
                        TextEntry::make('dropoff_address')->label('Drop-off')->placeholder('—'),
                        TextEntry::make('distance_km')->label('Straight-line km'),
                    ]),

                Section::make('Bolt ride')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('provider_ref')->label('Ride ID')->placeholder('Not booked yet')->copyable(),
                        TextEntry::make('provider_state')->label('Bolt state')->placeholder('—'),
                        TextEntry::make('provider_category')->label('Category')->placeholder('—'),
                        TextEntry::make('driver_line')
                            ->label('Driver')
                            ->state(fn (DeliveryJob $record) => collect([
                                $record->provider_driver['name'] ?? null,
                                $record->provider_driver['phone'] ?? null,
                                $record->provider_driver['plate'] ?? null,
                                $record->provider_driver['model'] ?? null,
                            ])->filter()->implode(' · ') ?: '—'),
                        TextEntry::make('tracking_url')
                            ->label('Tracking link')
                            ->state(fn (DeliveryJob $record) => $record->provider_driver['tracking_url'] ?? null)
                            ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                        TextEntry::make('booked_at')->dateTime()->placeholder('—'),
                        TextEntry::make('delivered_at')->label('Delivered (Bolt COMPLETED)')->dateTime()->placeholder('—'),
                        LivePosition::entry('live_position', fn (DeliveryJob $record) => $record->hasLiveLocation() ? (string) $record->provider_ref : null),
                    ]),

                Section::make('Money')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('estimate_range')
                            ->label("Bolt's estimate when the shop accepted")
                            ->state(fn (DeliveryJob $record) => $record->estimate_max === null ? '—'
                                : 'TSh '.number_format((float) $record->estimate_min).' – '.number_format((float) $record->estimate_max)),
                        TextEntry::make('fee')->label('Customer paid (upper bound)')->money('TZS', divideBy: 1),
                        TextEntry::make('final_cost')->label('Bolt receipt')->money('TZS', divideBy: 1)->placeholder('Not received yet'),
                        TextEntry::make('cost_difference')->label('Difference (paid − receipt)')->money('TZS', divideBy: 1)->placeholder('—'),
                        TextEntry::make('invoice')
                            ->label('Bolt invoice')
                            ->state(fn (DeliveryJob $record) => $record->receipt['invoice_url'] ?? null)
                            ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                        TextEntry::make('refund')
                            ->label('Refund to customer')
                            ->state(fn (DeliveryJob $record) => $record->refund_amount
                                ? 'TSh '.number_format((int) $record->refund_amount).' ('.$record->refund_status.')'
                                : '—'),
                    ]),

                PaymentAttempts::section(fn (DeliveryJob $record) => $record->payments()->get()),

                Section::make('Timeline (including every Bolt error)')
                    ->schema([
                        TextEntry::make('timeline_display')
                            ->label('')
                            ->state(fn (DeliveryJob $record) => $record->events()->get()
                                ->map(fn ($e) => $e->created_at->format('d M H:i:s').' · '.$e->actor_role.' · '
                                    .($e->type === 'status' ? "{$e->from_status} → {$e->to_status}" : $e->type)
                                    .($e->note ? ' · '.$e->note : '')
                                    .(isset($e->meta['x-bolt-tracking']) ? ' · x-bolt-tracking '.$e->meta['x-bolt-tracking'] : ''))
                                ->implode("\n"))
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ]);
    }
}
