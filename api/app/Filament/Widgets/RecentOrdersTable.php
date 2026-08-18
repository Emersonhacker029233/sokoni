<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** "Recent orders table" (CLAUDE.md admin rebuild, Section 5) — each row links straight to the order. */
class RecentOrdersTable extends TableWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent orders')
            ->query(Order::query()->with(['buyer', 'seller'])->latest()->limit(10))
            ->paginated(false)
            ->recordUrl(fn (Order $record) => route('filament.admin.resources.orders.view', $record))
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('buyer.name')->label('Buyer'),
                TextColumn::make('seller.shop_name')->label('Seller'),
                TextColumn::make('total')->money('TZS', divideBy: 1),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'cancelled' => 'danger',
                    'pending' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('created_at')->label('Placed')->since(),
            ]);
    }
}
