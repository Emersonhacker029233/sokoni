<?php

namespace App\Filament\Widgets;

use App\Models\SellerProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** "Pending verifications... as actionable lists, each row linking straight to the item" (CLAUDE.md admin rebuild, Section 5). */
class PendingVerificationsTable extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = ['md' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pending verifications')
            ->query(SellerProfile::query()->where('status', 'pending')->with('user')->oldest()->limit(10))
            ->paginated(false)
            ->recordUrl(fn () => route('filament.admin.pages.seller-verification'))
            ->columns([
                TextColumn::make('shop_name')->label('Shop'),
                TextColumn::make('user.name')->label('Owner'),
                TextColumn::make('created_at')->label('Waiting')->since(),
            ])
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('No sellers are pending verification.');
    }
}
