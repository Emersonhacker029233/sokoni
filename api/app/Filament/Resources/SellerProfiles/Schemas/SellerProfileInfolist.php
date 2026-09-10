<?php

namespace App\Filament\Resources\SellerProfiles\Schemas;

use App\Models\SellerProfile;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The seller verification queue's core screen. B1/B2 (tester feedback):
 * verification is now the typed NIDA number alone — the NIDA photo and
 * business licence evidence are gone entirely, here and in the queue page.
 * Verify/reject actions also live on this page's header (see
 * ViewSellerProfile::getHeaderActions()), not only on the list table —
 * this is the only screen that actually shows the evidence a reviewer
 * needs, so it must be able to act on it directly.
 */
class SellerProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Business')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Owner'),
                        TextEntry::make('user.phone')->label('Phone')->placeholder('-'),
                        TextEntry::make('shop_name'),
                        TextEntry::make('handle')->prefix('@'),
                        TextEntry::make('category.name_en')->label('Category')->placeholder('-'),
                        TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                            'verified' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        }),
                        TextEntry::make('bio')->columnSpanFull()->placeholder('-'),
                    ]),

                Section::make('Location')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')->placeholder('Not set'),
                        TextEntry::make('district')->placeholder('-'),
                        TextEntry::make('region')->placeholder('-'),
                        TextEntry::make('coordinates')
                            ->label('Map')
                            ->state(fn (SellerProfile $record) => $record->hasLocation() ? 'Open in Google Maps' : '-')
                            ->url(
                                fn (SellerProfile $record) => $record->hasLocation()
                                    ? "https://www.google.com/maps?q={$record->lat},{$record->lng}"
                                    : null
                            )
                            ->openUrlInNewTab(),
                    ]),

                Section::make('Identity')
                    ->description('The typed NIDA number is the entire basis of verification — no photo evidence or licence is collected.')
                    ->schema([
                        TextEntry::make('nida_number')->label('NIDA number')->placeholder('-'),
                        TextEntry::make('rejection_reason')->placeholder('-')->visible(
                            fn (SellerProfile $record) => $record->status === 'rejected'
                        ),
                    ]),
            ]);
    }
}
