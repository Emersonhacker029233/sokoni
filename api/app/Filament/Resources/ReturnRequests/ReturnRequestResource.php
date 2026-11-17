<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Filament\Resources\ReturnRequests\Schemas\ReturnRequestInfolist;
use App\Filament\Resources\ReturnRequests\Tables\ReturnRequestsTable;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnActionException;
use App\Services\Returns\ReturnService;
use App\Support\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Returns queue (docs/delivery/RETURNS.md): every return request with the
 * buyer's photos and comment, the shop's decision, who pays the Bolt ride
 * back and the ride itself. "Needs Sokoni" first: the shop didn't answer in
 * 48 hours, the buyer disputed a rejection, or the shop blocked the buyer.
 * Admins decide (accept, uphold the rejection / close), set who pays, and
 * hide content like any other report.
 */
class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static \UnitEnum|string|null $navigationGroup = 'Trust & Safety';

    protected static ?string $navigationLabel = 'Returns';

    protected static ?string $modelLabel = 'return';

    protected static ?string $slug = 'returns';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_admin;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReturnRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
            'view' => ViewReturnRequest::route('/{record}'),
        ];
    }

    public static function needsAdmin(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', 'disputed')
            ->orWhere(fn (Builder $r) => $r->where('status', 'requested')->whereNotNull('escalated_at')));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::needsAdmin(ReturnRequest::query())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function acceptAction(): Action
    {
        return Action::make('acceptReturn')
            ->label('Accept return')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ReturnRequest $record) => in_array($record->status, ['requested', 'rejected', 'disputed'], true))
            ->requiresConfirmation()
            ->modalDescription('Overrides the shop. The Bolt ride back is priced and the buyer is told what to do next.')
            ->schema([Textarea::make('note')->label('Decision (shown to both sides)')->required()->maxLength(1000)])
            ->action(fn (ReturnRequest $record, array $data) => static::run($record, 'return.accepted', $data['note'],
                fn (ReturnService $returns) => $returns->accept($record, Auth::user(), 'admin', $data['note'])));
    }

    public static function closeAction(): Action
    {
        return Action::make('closeReturn')
            ->label(fn (ReturnRequest $record) => $record->status === 'disputed' ? 'Uphold rejection' : 'Close return')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ReturnRequest $record) => $record->status !== 'closed')
            ->requiresConfirmation()
            ->schema([Textarea::make('note')->label('Reason (shown to both sides)')->required()->maxLength(1000)])
            ->action(fn (ReturnRequest $record, array $data) => static::run($record, 'return.closed', $data['note'],
                fn (ReturnService $returns) => $returns->close($record, Auth::user(), $data['note'])));
    }

    public static function payerAction(): Action
    {
        return Action::make('setPayer')
            ->label('Who pays the ride back')
            ->icon('heroicon-o-banknotes')
            ->visible(fn (ReturnRequest $record) => $record->fee_status !== 'paid' && $record->provider_ref === null && $record->status !== 'closed')
            ->fillForm(fn (ReturnRequest $record) => ['payer' => $record->payer])
            ->schema([
                Select::make('payer')->options(['buyer' => 'Buyer (changed mind)', 'seller' => 'Seller (owed by seller)'])->required(),
            ])
            ->action(fn (ReturnRequest $record, array $data) => static::run($record, 'return.payer', $data['payer'],
                fn (ReturnService $returns) => $returns->setPayer($record, Auth::user(), $data['payer'])));
    }

    /** Moderation, same as Report > Hide content: the comment and photos disappear for the shop. */
    public static function hideAction(): Action
    {
        return Action::make('hideContent')
            ->label(fn (ReturnRequest $record) => $record->is_hidden ? 'Show comment and photos' : 'Hide comment and photos')
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (ReturnRequest $record) {
                $record->forceFill(['is_hidden' => ! $record->is_hidden])->save();
                ActivityLogger::record(Auth::user(), $record->is_hidden ? 'return.hidden' : 'return.unhidden', $record);
                Notification::make()->title($record->is_hidden ? 'Hidden from the shop' : 'Visible again')->success()->send();
            });
    }

    /** @param callable(ReturnService): mixed $do */
    private static function run(ReturnRequest $record, string $log, string $reason, callable $do): void
    {
        try {
            $do(app(ReturnService::class));
        } catch (ReturnActionException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('That did not work: '.$e->getMessage())->danger()->send();

            return;
        }
        ActivityLogger::record(Auth::user(), $log, $record, $reason);
        Notification::make()->title('Done')->success()->send();
    }
}
