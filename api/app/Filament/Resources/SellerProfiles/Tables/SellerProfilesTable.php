<?php

namespace App\Filament\Resources\SellerProfiles\Tables;

use App\Models\SellerProfile;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SellerProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Alphabetically "pending" < "rejected" < "verified", so this
            // puts the verification queue's actual work — pending sellers
            // — first without a bespoke ordering expression.
            ->defaultSort('status')
            ->columns([
                TextColumn::make('shop_name')
                    ->searchable()
                    ->description(fn (SellerProfile $record) => '@'.$record->handle),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('category.name_en')
                    ->label('Category'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('nida_number')
                    ->label('NIDA #')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SellerProfile $record) => $record->status !== 'verified')
                    ->requiresConfirmation()
                    ->action(function (SellerProfile $record) {
                        $record->forceFill([
                            'status' => 'verified',
                            'verified_at' => now(),
                            'rejection_reason' => null,
                        ])->save();
                        Notification::make()->title('Seller verified')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SellerProfile $record) => $record->status !== 'rejected')
                    ->schema([
                        Textarea::make('reason')->label('Reason')->required(),
                    ])
                    ->action(function (SellerProfile $record, array $data) {
                        $record->forceFill([
                            'status' => 'rejected',
                            'rejection_reason' => $data['reason'],
                            'verified_at' => null,
                        ])->save();
                        Notification::make()->title('Seller rejected')->warning()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
