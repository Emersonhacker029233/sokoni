<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code'),
                        TextEntry::make('buyer.name')->label('Buyer'),
                        TextEntry::make('seller.shop_name')->label('Seller'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('total')->money('TZS', divideBy: 1),
                        TextEntry::make('payment_status')->badge(),
                        TextEntry::make('payment_method')->label('Payment method'),
                        TextEntry::make('created_at')->label('Placed')->dateTime(),
                        TextEntry::make('cancelled_reason')->label('Cancellation reason')->placeholder('—')
                            ->visible(fn (Order $record) => $record->status === 'cancelled'),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('title_snapshot')->label('Product'),
                                TextEntry::make('qty')->label('Qty'),
                                TextEntry::make('price_snapshot')->label('Unit price')->money('TZS', divideBy: 1),
                            ])
                            ->columns(3),
                        TextEntry::make('subtotal')->money('TZS', divideBy: 1),
                        TextEntry::make('delivery_fee')->label('Delivery fee')->money('TZS', divideBy: 1),
                    ]),

                Section::make('Delivery')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('delivery_method')->badge(),
                        TextEntry::make('address')->placeholder('—'),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Status timeline')
                    ->schema([
                        TextEntry::make('timeline_display')
                            ->label('')
                            ->state(fn (Order $record) => collect($record->timeline())
                                ->map(fn ($step) => ucfirst($step['status']).' — '.$step['at']->format('d M Y, H:i'))
                                ->implode("\n"))
                            ->listWithLineBreaks(),
                    ]),

                Section::make('Conversation')
                    ->schema([
                        TextEntry::make('conversation.messages_count')
                            ->label('Messages')
                            ->state(fn (Order $record) => $record->conversation?->messages()->count() ?? 0),
                        TextEntry::make('conversation.last_message_at')
                            ->label('Last message')
                            ->dateTime()
                            ->placeholder('No messages yet'),
                    ])
                    ->visible(fn (Order $record) => $record->conversation !== null)
                    ->collapsible(),
            ]);
    }
}
