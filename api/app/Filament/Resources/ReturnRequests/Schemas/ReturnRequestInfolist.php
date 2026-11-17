<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

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
                        TextEntry::make('payer')->label('Paid by')->badge(),
                        TextEntry::make('fee')->label('Fee (upper bound)')->money('TZS', divideBy: 1)->placeholder('Not priced yet'),
                        TextEntry::make('fee_status')->label('Fee status')->placeholder('—'),
                        TextEntry::make('ride_status')->label('Ride')->placeholder('Not booked'),
                        TextEntry::make('provider_ref')->label('Bolt ride ID')->placeholder('—')->copyable(),
                        TextEntry::make('provider_state')->label('Bolt state')->placeholder('—'),
                        TextEntry::make('driver_line')
                            ->label('Driver')
                            ->state(fn (ReturnRequest $record) => collect([
                                $record->provider_driver['name'] ?? null,
                                $record->provider_driver['phone'] ?? null,
                                $record->provider_driver['plate'] ?? null,
                            ])->filter()->implode(' · ') ?: '—'),
                        TextEntry::make('tracking_url')
                            ->label('Bolt tracking link')
                            ->state(fn (ReturnRequest $record) => $record->provider_driver['tracking_url'] ?? null)
                            ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                        TextEntry::make('final_cost')->label('Bolt receipt')->money('TZS', divideBy: 1)->placeholder('—'),
                    ]),
            ]);
    }
}
