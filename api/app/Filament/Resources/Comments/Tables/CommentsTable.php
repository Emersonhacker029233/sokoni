<?php

namespace App\Filament\Resources\Comments\Tables;

use App\Models\Comment;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'product', 'parent.user'])->withCount('replies'))
            ->columns([
                TextColumn::make('product.title')
                    ->label('Product')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('user.name')
                    ->label('From')
                    ->searchable(),
                TextColumn::make('body')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('parent.user.name')
                    ->label('Reply to')
                    ->placeholder('— (root comment)')
                    ->description(fn (Comment $record) => $record->parent?->body ? str($record->parent->body)->limit(40) : null),
                TextColumn::make('replies_count')
                    ->label('Replies'),
                IconColumn::make('is_hidden')
                    ->label('Hidden')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_hidden'),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (Comment $record) => ! $record->is_hidden)
                    ->requiresConfirmation()
                    ->action(function (Comment $record) {
                        $record->forceFill(['is_hidden' => true])->save();
                        ActivityLogger::record(Auth::user(), 'comment.hidden', $record);
                        Notification::make()->title('Comment hidden')->success()->send();
                    }),
                Action::make('unhide')
                    ->label('Unhide')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Comment $record) => $record->is_hidden)
                    ->action(function (Comment $record) {
                        $record->forceFill(['is_hidden' => false])->save();
                        ActivityLogger::record(Auth::user(), 'comment.unhidden', $record);
                        Notification::make()->title('Comment unhidden')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
