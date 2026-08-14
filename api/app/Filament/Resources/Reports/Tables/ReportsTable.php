<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Models\Report;
use App\Services\Push\PushNotifier;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The moderation queue (CLAUDE.md feature 9/11). Resolving a report as
 * "upheld" feeds `ReportObserver`'s 3-strikes auto-hide; the four direct
 * actions below (hide / warn / suspend / ban) are the admin's manual
 * override for anything that shouldn't wait for a third strike.
 */
class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('status')
            ->columns([
                TextColumn::make('reportable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),
                TextColumn::make('reportable_id')
                    ->label('#')
                    ->numeric(),
                TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->searchable(),
                TextColumn::make('reason')
                    ->searchable(),
                TextColumn::make('note')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'upheld' => 'danger',
                        'dismissed' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'upheld' => 'Upheld',
                    'dismissed' => 'Dismissed',
                ]),
                SelectFilter::make('reportable_type')
                    ->label('Type')
                    ->options([
                        \App\Models\Product::class => 'Product',
                        \App\Models\SellerProfile::class => 'Shop',
                        \App\Models\Message::class => 'Message',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('uphold')
                    ->label('Uphold')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (Report $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Report $record) {
                        $record->forceFill([
                            'status' => 'upheld',
                            'resolved_by' => auth()->id(),
                            'resolution' => 'Upheld by admin review.',
                        ])->save();
                        Notification::make()->title('Report upheld')->success()->send();
                    }),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (Report $record) => $record->status === 'pending')
                    ->action(function (Report $record) {
                        $record->forceFill([
                            'status' => 'dismissed',
                            'resolved_by' => auth()->id(),
                            'resolution' => 'Dismissed — no action taken.',
                        ])->save();
                        Notification::make()->title('Report dismissed')->send();
                    }),
                Action::make('hide')
                    ->label('Hide content')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(
                        fn (Report $record) => $record->reportable !== null
                            && array_key_exists('is_hidden', $record->reportable->getAttributes())
                            && ! $record->reportable->is_hidden
                    )
                    ->requiresConfirmation()
                    ->action(function (Report $record) {
                        $record->reportable->forceFill(['is_hidden' => true])->save();
                        Notification::make()->title('Content hidden')->success()->send();
                    }),
                Action::make('warn')
                    ->label('Warn user')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (Report $record) => $record->offendingUser() !== null)
                    ->schema([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (Report $record, array $data) {
                        app(PushNotifier::class)->notify(
                            $record->offendingUser(),
                            'Warning from Sokoni',
                            $data['reason'],
                        );
                        Notification::make()->title('Warning sent')->success()->send();
                    }),
                Action::make('suspend')
                    ->label('Suspend user')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->visible(fn (Report $record) => $record->offendingUser() !== null)
                    ->schema([
                        TextInput::make('days')->numeric()->required()->default(7),
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (Report $record, array $data) {
                        $user = $record->offendingUser();
                        $user->forceFill([
                            'banned_at' => now(),
                            'banned_until' => now()->addDays((int) $data['days']),
                            'ban_reason' => $data['reason'],
                        ])->save();
                        $user->tokens()->delete();
                        app(PushNotifier::class)->notify($user, 'Account suspended', $data['reason']);
                        Notification::make()->title('User suspended')->success()->send();
                    }),
                Action::make('ban')
                    ->label('Ban user')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Report $record) => $record->offendingUser() !== null)
                    ->schema([
                        Textarea::make('reason')->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Report $record, array $data) {
                        $user = $record->offendingUser();
                        $user->forceFill([
                            'banned_at' => now(),
                            'banned_until' => null,
                            'ban_reason' => $data['reason'],
                        ])->save();
                        $user->tokens()->delete();
                        app(PushNotifier::class)->notify($user, 'Account banned', $data['reason']);
                        Notification::make()->title('User banned')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
