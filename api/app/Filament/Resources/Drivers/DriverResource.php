<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Pages\ViewDriver;
use App\Filament\Resources\Drivers\Schemas\DriverInfolist;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\DriverProfile;
use App\Services\Delivery\DriverReviewException;
use App\Services\Delivery\DriverReviewService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sokoni Driver applications and accounts (docs/delivery/PLAN.md phase 5,
 * brought forward). Drivers fill in everything themselves in the app; this
 * resource is the admin's review-and-decide surface: a queue (list), the
 * evidence (view page: NIDA number, licence, vehicle, document images) and
 * the decisions, which all go through DriverReviewService so the state
 * change, the activity log entry and the driver's notification stay
 * together. Admin role only (DriverProfilePolicy).
 */
class DriverResource extends Resource
{
    protected static ?string $model = DriverProfile::class;

    protected static ?string $slug = 'drivers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static \UnitEnum|string|null $navigationGroup = 'Trust & Safety';

    protected static ?string $navigationLabel = 'Drivers';

    protected static ?string $modelLabel = 'driver';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'activeVehicle']);
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof DriverProfile ? ($record->user?->name ?? "Driver #{$record->id}") : null;
    }

    /** How many drivers are waiting on a decision, on the nav item. */
    public static function getNavigationBadge(): ?string
    {
        $count = self::reviewQueue(DriverProfile::query())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Submitted applications, plus approved drivers whose new vehicle hasn't been checked. */
    public static function reviewQueue(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $pending) => $pending->where('status', 'pending')->whereNotNull('submitted_at'))
            ->orWhere(fn (Builder $vehicle) => $vehicle
                ->where('status', 'approved')
                ->whereHas('vehicles', fn (Builder $v) => $v->where('is_active', true)->whereNull('verified_at'))));
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(fn (DriverProfile $record) => DriverReviewService::isVehicleCheck($record) ? 'Approve vehicle' : 'Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('review')
            ->visible(fn (DriverProfile $record) => DriverReviewService::needsReview($record))
            ->requiresConfirmation()
            ->modalHeading(fn (DriverProfile $record) => DriverReviewService::isVehicleCheck($record)
                ? 'Approve '.$record->user->name.'\'s new vehicle?'
                : 'Approve '.$record->user->name.'?')
            ->modalDescription(fn (DriverProfile $record) => 'Their '.self::vehicleSummary($record).' is marked verified and they can go online straight away. '
                .'They are told in the app and by push, or by SMS when push can\'t reach them.')
            ->action(function (DriverProfile $record) {
                self::run(fn () => app(DriverReviewService::class)->approve($record, auth()->user()), $record->user->name.' approved');
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('review')
            ->visible(fn (DriverProfile $record) => DriverReviewService::needsReview($record))
            ->modalHeading(fn (DriverProfile $record) => 'Reject '.$record->user->name.'?')
            ->modalDescription('The driver sees this reason in the app, next to "Fix and resubmit", and gets it by push or SMS.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (shown to the driver)')
                    ->placeholder('e.g. The NIDA card photo is blurred. Retake it in good light with all four corners visible.')
                    ->required()
                    ->maxLength(250)
                    ->rows(3),
            ])
            ->action(function (DriverProfile $record, array $data) {
                self::run(fn () => app(DriverReviewService::class)->reject($record, auth()->user(), $data['reason']), $record->user->name.' rejected', 'warning');
            });
    }

    public static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->authorize('review')
            ->visible(fn (DriverProfile $record) => $record->status === 'approved')
            ->modalHeading(fn (DriverProfile $record) => 'Suspend '.$record->user->name.'?')
            ->modalDescription('They go offline and can\'t receive deliveries until reinstated. Not possible while they are on a delivery.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (shown to the driver)')
                    ->required()
                    ->maxLength(250)
                    ->rows(3),
            ])
            ->action(function (DriverProfile $record, array $data) {
                self::run(fn () => app(DriverReviewService::class)->suspend($record, auth()->user(), $data['reason']), $record->user->name.' suspended', 'warning');
            });
    }

    public static function reinstateAction(): Action
    {
        return Action::make('reinstate')
            ->label('Reinstate')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->authorize('review')
            ->visible(fn (DriverProfile $record) => $record->status === 'suspended')
            ->requiresConfirmation()
            ->modalHeading(fn (DriverProfile $record) => 'Reinstate '.$record->user->name.'?')
            ->modalDescription('Their account becomes approved again and they can go online.')
            ->action(function (DriverProfile $record) {
                self::run(fn () => app(DriverReviewService::class)->reinstate($record, auth()->user()), $record->user->name.' reinstated');
            });
    }

    public static function unlockPinAction(): Action
    {
        return Action::make('unlockPin')
            ->label('Unlock delivery PIN')
            ->icon('heroicon-o-lock-open')
            ->color('warning')
            ->authorize('review')
            ->visible(fn (DriverProfile $record) => $record->activeJob()?->pin_locked_at !== null)
            ->requiresConfirmation()
            ->modalHeading('Unlock delivery PIN entry?')
            ->modalDescription('Do this only after calling the customer and confirming the hand-over is genuine. '
                .'The attempt counter resets and the PIN stays the same.')
            ->action(function (DriverProfile $record) {
                $job = $record->activeJob();
                if ($job === null) {
                    Notification::make()->title('This driver has no delivery in progress')->danger()->send();

                    return;
                }
                self::run(fn () => app(DriverReviewService::class)->unlockPin($job, auth()->user()), "PIN entry unlocked on delivery #{$job->id}");
            });
    }

    /** e.g. "boda boda MC123ABC". */
    public static function vehicleSummary(DriverProfile $record): string
    {
        $vehicle = $record->activeVehicle;
        if ($vehicle === null) {
            return 'vehicle';
        }

        return trim(str_replace('_', ' ', $vehicle->type).' '.($vehicle->plate_number ?? ''));
    }

    /** Runs a decision and turns a refused one into a red toast with the reason. */
    private static function run(callable $decision, string $success, string $tone = 'success'): void
    {
        try {
            $decision();
        } catch (DriverReviewException $e) {
            Notification::make()->title('Not done')->body($e->getMessage())->danger()->send();

            return;
        }
        Notification::make()->title($success)->{$tone}()->send();
    }

    public static function infolist(Schema $schema): Schema
    {
        return DriverInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'view' => ViewDriver::route('/{record}'),
        ];
    }
}
