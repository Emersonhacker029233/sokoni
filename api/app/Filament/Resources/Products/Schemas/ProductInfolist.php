<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The product detail page. `description` is a single free-text field the
 * seller writes in whatever language they choose — CLAUDE.md's data model
 * only makes *category* names bilingual (`name_en`/`name_sw`); there is no
 * `description_en`/`description_sw` on products to show "in both
 * languages" as the task's wording assumes. Shown as the one real
 * description field that exists rather than inventing a second one the
 * schema doesn't have — same principle as the dashboard's "don't invent a
 * metric the schema can't support".
 */
class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Media')
                    ->schema([
                        RepeatableEntry::make('media')
                            ->label('')
                            ->schema([
                                ImageEntry::make('thumb_path')->label('')->height(120),
                                TextEntry::make('type')->label('')->badge(),
                            ])
                            ->columns(6)
                            ->grid(6),
                    ])
                    ->visible(fn (Product $record) => $record->media->isNotEmpty()),

                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('seller.shop_name')->label('Shop'),
                        TextEntry::make('category.name_en')->label('Category')->placeholder('—'),
                        TextEntry::make('condition')->badge(),
                        TextEntry::make('price')->money('TZS', divideBy: 1),
                        TextEntry::make('stock'),
                        TextEntry::make('views'),
                        TextEntry::make('created_at')->label('Listed')->dateTime(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('No description.'),
                    ]),

                Section::make('Order history')
                    ->schema([
                        RepeatableEntry::make('orderItems')
                            ->label('')
                            ->schema([
                                TextEntry::make('order.code')->label('Order'),
                                TextEntry::make('order.status')->label('Status')->badge(),
                                TextEntry::make('qty')->label('Qty'),
                                TextEntry::make('price_snapshot')->label('Price')->money('TZS', divideBy: 1),
                                TextEntry::make('order.created_at')->label('Placed')->dateTime(),
                            ])
                            ->columns(5),
                    ])
                    ->visible(fn (Product $record) => $record->orderItems->isNotEmpty())
                    ->collapsible(),

                Section::make('Reports')
                    ->schema([
                        RepeatableEntry::make('reports')
                            ->label('')
                            ->schema([
                                TextEntry::make('reason')->badge(),
                                TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                                    'upheld' => 'danger',
                                    'dismissed' => 'gray',
                                    default => 'warning',
                                }),
                                TextEntry::make('reporter.name')->label('Reported by'),
                                TextEntry::make('created_at')->label('When')->dateTime(),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (Product $record) => $record->reports->isNotEmpty())
                    ->collapsible(),
            ]);
    }
}
