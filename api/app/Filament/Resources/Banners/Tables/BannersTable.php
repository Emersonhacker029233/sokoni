<?php

namespace App\Filament\Resources\Banners\Tables;

use App\Models\Banner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->square(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('position')
                    ->badge(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->placeholder('Immediately')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),
                // Part 1 (client feedback, urgent): "an uploaded banner
                // never appears" — a plain is_active icon didn't say
                // *why* a banner isn't showing (inactive vs. not
                // scheduled yet vs. expired), which read as identical to
                // a genuinely broken upload. One glance now says which.
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Banner $record) => $record->liveStatusLabel())
                    ->badge()
                    ->color(function (Banner $record) {
                        $label = $record->liveStatusLabel();

                        return match (true) {
                            $label === 'Live now' => 'success',
                            str_starts_with($label, 'Scheduled') => 'warning',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('impressions_count')
                    ->label('Impressions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('position')
                    ->options([
                        'home_hero' => 'Home — hero',
                        'home_mid' => 'Home — mid-page',
                        'category_top' => 'Category — top',
                        'sidebar' => 'Sidebar',
                        // Part B (client feedback): noon.com-pattern ad inventory.
                        'search_background' => 'Home — search background',
                        'category_strip_side' => 'Home — category strip side',
                        'near_you_side' => 'Home — Near you side',
                        // Part 4 (client feedback): "In Focus" advertising band.
                        'in_focus' => 'Home — In Focus poster',
                    ]),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
