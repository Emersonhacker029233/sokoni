<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('avatar')
                    ->placeholder('-'),
                TextEntry::make('provider')
                    ->placeholder('-'),
                TextEntry::make('provider_id')
                    ->placeholder('-'),
                TextEntry::make('locale'),
                TextEntry::make('terms_accepted_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('terms_version')
                    ->placeholder('-'),
                TextEntry::make('banned_at')
                    ->dateTime()
                    ->placeholder('-'),
                IconEntry::make('is_admin')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('ban_reason')
                    ->placeholder('-'),
                TextEntry::make('banned_until')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
