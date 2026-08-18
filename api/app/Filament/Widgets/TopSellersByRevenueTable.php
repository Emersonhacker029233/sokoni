<?php

namespace App\Filament\Widgets;

use App\Models\SellerProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** "Top 10 sellers by revenue — table" (CLAUDE.md admin rebuild, Section 5). Real `SUM` over each seller's completed orders, lifetime — not a guess, an aggregate query. */
class TopSellersByRevenueTable extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top 10 sellers by revenue')
            ->query(
                SellerProfile::query()
                    ->withSum(['orders as revenue' => fn (Builder $q) => $q->where('status', 'completed')], 'total')
                    ->orderByDesc('revenue')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('shop_name')
                    ->label('Shop')
                    ->url(fn (SellerProfile $record) => route('filament.admin.resources.seller-profiles.view', $record)),
                TextColumn::make('handle')->prefix('@'),
                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->money('TZS', divideBy: 1)
                    ->state(fn (SellerProfile $record) => $record->revenue ?? 0),
                TextColumn::make('rating_avg')->label('Rating')->formatStateUsing(fn (?string $state) => $state ? round((float) $state, 1).' ★' : '—'),
            ]);
    }
}
