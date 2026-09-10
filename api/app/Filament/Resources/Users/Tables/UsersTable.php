<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Push\PushNotifier;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\RestoreAction;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    /**
     * C2 (tester feedback): "never allow deleting your own account or
     * another admin." `is_admin` (not the finer-grained `role`) is the
     * actual panel-access gate `User::canAccessPanel()` checks, and the
     * one boolean this table already surfaces (`IconColumn::make
     * ('is_admin')`) — so it's the deliberately conservative line: staff
     * accounts are covered by the same protection as full admins, not
     * just other admins specifically.
     */
    private static function isProtectedFromDeletion(User $record): bool
    {
        return $record->id === Auth::id() || $record->is_admin;
    }

    /**
     * C2: "cascades to shop/products/orders (state explicitly in
     * confirmation)." The shop and its products are soft-deleted right
     * alongside the user (recoverable together, or not at all — see the
     * SoftDeletes migration's own docblock). Orders are a judgement
     * call, not an oversight: an order is a two-party record, and
     * deleting it would corrupt the OTHER party's (buyer's or seller's)
     * own legitimate order history along with it — so orders are
     * deliberately preserved, never touched, and that's stated plainly
     * in the confirmation text rather than left ambiguous.
     */
    private static function cascadeSummary(User $record): array
    {
        $shop = $record->sellerProfile;
        $productCount = $shop ? $shop->products()->count() : 0;
        $orderCount = $record->orders()->count() + ($shop ? $shop->orders()->count() : 0);

        return compact('shop', 'productCount', 'orderCount');
    }

    private static function deletionModalDescription(User $record): string
    {
        ['shop' => $shop, 'productCount' => $productCount, 'orderCount' => $orderCount] = self::cascadeSummary($record);

        $lines = ["This removes {$record->name}'s account."];
        if ($shop) {
            $lines[] = "Their shop \"{$shop->shop_name}\" and its {$productCount} product(s) will be removed with it.";
        }
        $lines[] = $orderCount > 0
            ? "{$orderCount} order(s) involving this user will be preserved for record-keeping — they are NOT deleted."
            : 'This user has no orders on record.';
        $lines[] = 'This is recoverable (Trashed filter) until permanently purged.';

        return implode(' ', $lines);
    }

    private static function cascadeDelete(User $record): void
    {
        $shop = $record->sellerProfile;
        if ($shop) {
            $shop->products()->get()->each->delete();
            $shop->delete();
        }
        $record->delete();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('sellerProfile.shop_name')
                    ->label('Shop')
                    ->placeholder('-'),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (User $record) => $record->isBanned() ? ($record->banned_until ? 'Suspended' : 'Banned') : 'Active')
                    ->badge()
                    ->color(fn (User $record) => $record->isBanned() ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('banned')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('banned_at'),
                        false: fn ($query) => $query->whereNull('banned_at'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('warn')
                    ->label('Warn')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->schema([Textarea::make('reason')->required()])
                    ->action(function (User $record, array $data) {
                        app(PushNotifier::class)->notify($record, 'Warning from Sokoni', $data['reason']);
                        Notification::make()->title('Warning sent')->success()->send();
                    }),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->visible(fn (User $record) => ! $record->isBanned())
                    ->schema([
                        TextInput::make('days')->numeric()->required()->default(7),
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->forceFill([
                            'banned_at' => now(),
                            'banned_until' => now()->addDays((int) $data['days']),
                            'ban_reason' => $data['reason'],
                        ])->save();
                        $record->tokens()->delete();
                        app(PushNotifier::class)->notify($record, 'Account suspended', $data['reason']);
                        Notification::make()->title('User suspended')->success()->send();
                    }),
                Action::make('ban')
                    ->label('Ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record) => ! $record->isBanned())
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->required()])
                    ->action(function (User $record, array $data) {
                        $record->forceFill([
                            'banned_at' => now(),
                            'banned_until' => null,
                            'ban_reason' => $data['reason'],
                        ])->save();
                        $record->tokens()->delete();
                        app(PushNotifier::class)->notify($record, 'Account banned', $data['reason']);
                        Notification::make()->title('User banned')->success()->send();
                    }),
                Action::make('unban')
                    ->label('Unban')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => $record->isBanned())
                    ->action(function (User $record) {
                        $record->forceFill(['banned_at' => null, 'banned_until' => null, 'ban_reason' => null])->save();
                        Notification::make()->title('User restored')->success()->send();
                    }),
                // C2 (tester feedback): "admin can delete users, not just
                // hide/ban" — soft delete (recoverable), cascading to the
                // user's own shop/products (orders are deliberately
                // preserved — see cascadeDelete()'s docblock), naming the
                // cascade explicitly in the confirmation and requiring a
                // reason. Never shown for your own account or another
                // admin/staff account.
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (User $record) => ! self::isProtectedFromDeletion($record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => "Delete {$record->name}?")
                    ->modalDescription(fn (User $record) => self::deletionModalDescription($record))
                    ->schema([Textarea::make('reason')->label('Reason')->required()])
                    ->action(function (User $record, array $data) {
                        self::cascadeDelete($record);
                        ActivityLogger::record(Auth::user(), 'user.deleted', $record, $data['reason']);
                        Notification::make()->title('User deleted')->success()->send();
                    }),
                RestoreAction::make()
                    ->action(function (User $record) {
                        $record->restore();
                        ActivityLogger::record(Auth::user(), 'user.restored', $record);
                        Notification::make()->title('User restored')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Deletes each selected account (cascading to their shop/products; orders are preserved, not deleted). Your own account and any admin/staff account are always skipped. This is recoverable (Trashed filter) until permanently purged.')
                        ->schema([Textarea::make('reason')->label('Reason')->required()])
                        ->action(function (Collection $records, array $data) {
                            $deletable = $records->reject(fn (User $record) => self::isProtectedFromDeletion($record));
                            $skipped = $records->count() - $deletable->count();

                            foreach ($deletable as $record) {
                                self::cascadeDelete($record);
                                ActivityLogger::record(Auth::user(), 'user.deleted', $record, $data['reason'], ['bulk' => true]);
                            }

                            $title = "{$deletable->count()} user(s) deleted";
                            if ($skipped > 0) {
                                $title .= " ({$skipped} skipped — your own account and admin/staff accounts can't be deleted here)";
                            }
                            Notification::make()->title($title)->success()->send();
                        }),
                ]),
            ]);
    }
}
