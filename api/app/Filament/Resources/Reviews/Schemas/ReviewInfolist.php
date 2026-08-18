<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Filament\Resources\Reviews\ReviewProductSummary;
use App\Models\Review;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rating')->formatStateUsing(fn (int $state) => str_repeat('★', $state)),
                        TextEntry::make('created_at')->label('Date')->dateTime(),
                        TextEntry::make('buyer.name')->label('Buyer'),
                        TextEntry::make('seller.shop_name')->label('Seller'),
                        TextEntry::make('product_summary')
                            ->label('Product(s)')
                            ->state(fn (Review $record) => ReviewProductSummary::for($record))
                            ->columnSpanFull(),
                        TextEntry::make('comment')->placeholder('No comment left.')->columnSpanFull(),
                    ]),
                Section::make('Seller reply')
                    ->schema([
                        TextEntry::make('reply')->label(''),
                        TextEntry::make('replied_at')->label('Replied')->dateTime(),
                    ])
                    ->visible(fn (Review $record) => $record->hasReply()),
            ]);
    }
}
