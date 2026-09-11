<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * D1 (tester feedback): "edit name/phone/email/role/status." Create stays
 * staff/admin-only by convention (buyers/sellers sign up through the app),
 * but Edit is reachable for ANY user record — a previous version of this
 * form only had name/email/password/is_admin, which broke as soon as it
 * was opened for a buyer or OTP-only seller: `email` was `required()` even
 * though the column is nullable and plenty of real accounts have none.
 * `status` (ban/suspend/unban) is deliberately NOT a field here — it stays
 * the dedicated UsersTable row actions, which capture a reason (and a
 * suspension duration) and push-notify the user; collapsing that into a
 * plain toggle here would be a real regression, not a simplification.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->tel()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state)),
                // is_admin/role are deliberately absent from User's
                // #[Fillable] list — the same reason banned_at/ban_reason
                // are only ever set via forceFill() in UsersTable, never
                // mass-assigned. CreateUser/EditUser forceFill these two
                // explicitly after the form's other fields save normally.
                Toggle::make('is_admin')
                    ->label('Admin (can access this panel)')
                    ->live()
                    ->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'staff' => 'Staff'])
                    ->visible(fn (Get $get) => (bool) $get('is_admin'))
                    ->required(fn (Get $get) => (bool) $get('is_admin')),
            ]);
    }
}
