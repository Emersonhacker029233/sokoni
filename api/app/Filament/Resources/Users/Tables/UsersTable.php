<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Push\PushNotifier;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
