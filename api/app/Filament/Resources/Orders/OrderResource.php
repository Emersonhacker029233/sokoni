<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Support\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * "An order is a record, not a form" (CLAUDE.md admin rebuild, Section
 * 4) — no create/edit pages, and no field anywhere in this resource ever
 * touches price/subtotal/total. The only mutation available is
 * cancellation, which goes through the exact same forceFill transition
 * path `OrderController::updateStatus` uses for a buyer/seller-initiated
 * cancellation (see `Order::TRANSITIONS`/`canTransitionTo()`), just
 * admin-initiated with a logged reason instead.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static \UnitEnum|string|null $navigationGroup = 'Commerce';

    // See CategoryResource for why this matters.
    protected static ?string $recordTitleAttribute = 'code';

    /** "Global search across products, shops, users and orders" (CLAUDE.md admin rebuild, Section 6). */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'buyer.name', 'seller.shop_name'];
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record) => $record->canTransitionTo('cancelled'))
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')->label('Reason')->required(),
            ])
            ->action(function (Order $record, array $data) {
                $record->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => $data['reason'],
                ])->save();
                ActivityLogger::record(Auth::user(), 'order.cancelled', $record, $data['reason']);
                Notification::make()->title('Order cancelled')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
