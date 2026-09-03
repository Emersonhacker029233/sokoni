<?php

namespace App\Filament\Resources\SellerProfiles\Schemas;

use App\Models\SellerProfile;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * The seller verification queue's core screen (CLAUDE.md feature 9: "the
 * seller verification queue (NIDA image + licence + map location side by
 * side)"). Verify/reject actions also live on this page's header (see
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

                // Side by side: the two pieces of identity evidence a
                // reviewer compares before approving.
                Section::make('Identity & licence')
                    ->description('NIDA number and photo are the actual basis of verification. The licence is an optional extra a seller may also provide.')
                    ->schema([
                        Grid::make(2)->schema([
                            ImageEntry::make('nida_image')
                                ->label('NIDA photo (basis of verification)')
                                ->disk('public')
                                ->placeholder('Not submitted')
                                ->height(320),
                            TextEntry::make('licence_file')
                                ->label('Business licence (optional extra)')
                                ->state(fn (SellerProfile $record) => $record->licence_file ? 'Open file' : null)
                                ->placeholder('Not submitted')
                                ->url(
                                    fn (SellerProfile $record) => $record->licence_file
                                        ? Storage::disk('public')->url($record->licence_file)
                                        : null
                                )
                                ->openUrlInNewTab(),
                        ]),
                        TextEntry::make('nida_number')->label('NIDA number')->placeholder('-'),
                        TextEntry::make('rejection_reason')->placeholder('-')->visible(
                            fn (SellerProfile $record) => $record->status === 'rejected'
                        ),
                    ]),
            ]);
    }
}
