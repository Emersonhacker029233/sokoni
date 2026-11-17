<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\Report;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Everything a moderator needs to decide in one place: the reported
 * content itself (text, pictures, video), who posted it and how often
 * they've been reported before, and how long is left of the 24 hours.
 */
class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reported content')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('preview_title')
                            ->label('')
                            ->weight('bold')
                            ->state(fn (Report $record) => $record->contentPreview()['title']),
                        TextEntry::make('preview_text')
                            ->label('Text')
                            ->placeholder('No text')
                            ->state(fn (Report $record) => $record->contentPreview()['text']),
                        ImageEntry::make('preview_images')
                            ->label('Pictures')
                            ->height(160)
                            ->placeholder('No pictures')
                            ->state(fn (Report $record) => $record->contentPreview()['images']),
                        TextEntry::make('preview_video')
                            ->label('Video')
                            ->state(fn (Report $record) => $record->contentPreview()['video'])
                            ->url(fn (Report $record) => $record->contentPreview()['video'], shouldOpenInNewTab: true)
                            ->placeholder('No video'),
                        TextEntry::make('visibility')
                            ->label('Visibility')
                            ->badge()
                            ->state(fn (Report $record) => match (true) {
                                $record->reportable === null => 'Deleted',
                                $record->canHideContent() && $record->reportable->is_hidden => 'Hidden',
                                default => 'Visible',
                            })
                            ->color(fn (string $state) => $state === 'Visible' ? 'warning' : 'gray'),
                    ]),
                Section::make('Report')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('due')
                            ->label('Act within 24 hours')
                            ->badge()
                            ->state(fn (Report $record) => $record->dueLabel())
                            ->color(fn (Report $record) => $record->dueColor()),
                        TextEntry::make('reportable_type')
                            ->label('Content type')
                            ->formatStateUsing(fn (Report $record) => ucfirst($record->typeName()).' #'.$record->reportable_id),
                        TextEntry::make('reason')
                            ->formatStateUsing(fn (Report $record) => $record->reasonLabel()),
                        TextEntry::make('note')
                            ->label('Note from reporter')
                            ->placeholder('-'),
                        TextEntry::make('reporter.name')
                            ->label('Reported by'),
                        TextEntry::make('author')
                            ->label('Posted by')
                            ->state(fn (Report $record) => self::authorLine($record))
                            ->url(fn (Report $record) => $record->offendingUser()
                                ? route('filament.admin.resources.users.view', $record->offendingUser())
                                : null),
                        TextEntry::make('created_at')
                            ->label('Filed')
                            ->dateTime(),
                        TextEntry::make('alerted_at')
                            ->label('Moderators alerted')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                Section::make('Resolution')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'upheld' => 'danger',
                                'dismissed' => 'gray',
                                default => 'warning',
                            }),
                        TextEntry::make('resolvedBy.name')
                            ->label('Resolved by')
                            ->placeholder('-'),
                        TextEntry::make('resolved_at')
                            ->label('Resolved')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('resolution')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    private static function authorLine(Report $record): string
    {
        $author = $record->offendingUser();
        if ($author === null) {
            return '-';
        }

        $earlier = Report::againstUser($author)->whereKeyNot($record->id)->count();
        $flags = array_filter([
            $author->isBanned() ? 'banned' : null,
            $earlier > 0 ? "{$earlier} other report(s) about them" : null,
        ]);

        return $author->name.' (user #'.$author->id.')'.($flags ? ' — '.implode(', ', $flags) : '');
    }
}
