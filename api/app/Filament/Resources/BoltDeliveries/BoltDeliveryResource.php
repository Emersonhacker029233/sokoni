<?php

namespace App\Filament\Resources\BoltDeliveries;

use App\Filament\Resources\BoltDeliveries\Pages\ListBoltDeliveries;
use App\Filament\Resources\BoltDeliveries\Pages\ViewBoltDelivery;
use App\Filament\Resources\BoltDeliveries\Schemas\BoltDeliveryInfolist;
use App\Filament\Resources\BoltDeliveries\Tables\BoltDeliveriesTable;
use App\Models\DeliveryJob;
use App\Services\Delivery\DeliveryActionException;
use App\Services\Delivery\DeliveryJobService;
use App\Support\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Bolt deliveries (docs/delivery/BOLT.md): what the customer paid against
 * what Bolt charged, the Bolt ride and its tracking link, and the shared
 * timeline including every Bolt error. Records only; the three actions
 * (record a fee paid outside the app, mark a refund done, cancel) are
 * admin-role only and logged.
 */
class BoltDeliveryResource extends Resource
{
    protected static ?string $model = DeliveryJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static \UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Bolt deliveries';

    protected static ?string $modelLabel = 'Bolt delivery';

    protected static ?string $slug = 'bolt-deliveries';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('provider', DeliveryJob::PROVIDER_BOLT);
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_admin;
    }

    public static function infolist(Schema $schema): Schema
    {
        return BoltDeliveryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BoltDeliveriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoltDeliveries::route('/'),
            'view' => ViewBoltDelivery::route('/{record}'),
        ];
    }

    /** Needs a person: Bolt couldn't deliver, a cancel didn't reach Bolt, or a refund is still to be paid. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::needsAttention(DeliveryJob::query()->where('provider', DeliveryJob::PROVIDER_BOLT))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function needsAttention(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('status', ['failed', 'no_driver_found'])
            ->orWhere('refund_status', 'pending')
            ->orWhereHas('events', fn (Builder $e) => $e->where('type', 'provider_cancel_failed')));
    }

    /**
     * The buyer paid the delivery fee some other way (cash at the office,
     * M-Pesa to the company number). Moves the delivery on exactly as a
     * confirmed in-app payment would.
     */
    public static function recordFeePaidAction(): Action
    {
        return Action::make('recordFeePaid')
            ->label('Record fee paid')
            ->icon('heroicon-o-banknotes')
            ->visible(fn (DeliveryJob $record) => Auth::user()?->isAdminRole() && $record->status === 'pending_payment')
            ->requiresConfirmation()
            ->modalDescription('Only when the money has reached Sokoni. The Bolt ride is booked as soon as the shop marks the order ready.')
            ->schema([
                TextInput::make('reference')->label('Payment reference (M-Pesa code, receipt no.)')->required()->maxLength(80),
            ])
            ->action(function (DeliveryJob $record, array $data) {
                app(DeliveryJobService::class)->markFeePaid($record, 'manual:'.$data['reference'], Auth::user());
                ActivityLogger::record(Auth::user(), 'delivery.fee_recorded', $record, $data['reference']);
                Notification::make()->title('Fee recorded as paid')->success()->send();
            });
    }

    public static function markRefundedAction(): Action
    {
        return Action::make('markRefunded')
            ->label('Mark refund paid')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (DeliveryJob $record) => Auth::user()?->isAdminRole() && $record->refund_status === 'pending')
            ->requiresConfirmation()
            ->schema([
                TextInput::make('reference')->label('Refund reference')->required()->maxLength(80),
            ])
            ->action(function (DeliveryJob $record, array $data) {
                DeliveryJob::query()->whereKey($record->id)->update(['refund_status' => 'refunded']);
                ActivityLogger::record(Auth::user(), 'delivery.refunded', $record, $data['reference'], ['amount' => $record->refund_amount]);
                Notification::make()->title('Refund marked as paid')->success()->send();
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancelDelivery')
            ->label('Cancel delivery')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (DeliveryJob $record) => Auth::user()?->isAdminRole() && ! $record->isTerminal())
            ->requiresConfirmation()
            ->modalDescription('Cancels the Bolt ride (if the parcel has not left the shop) and the order. A paid fee is marked for refund.')
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->maxLength(255),
            ])
            ->action(function (DeliveryJob $record, array $data) {
                try {
                    app(DeliveryJobService::class)->cancel($record, Auth::user(), 'admin', $data['reason']);
                } catch (DeliveryActionException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }
                ActivityLogger::record(Auth::user(), 'delivery.cancelled', $record, $data['reason']);
                Notification::make()->title('Delivery cancelled')->success()->send();
            });
    }
}
