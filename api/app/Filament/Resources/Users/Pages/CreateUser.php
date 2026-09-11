<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * `is_admin`/`role` are deliberately absent from User's `#[Fillable]`
     * list (same reason `banned_at`/`ban_reason` are set via forceFill()
     * in UsersTable, never mass-assigned) — the default
     * `Model::create($data)` this method replaces would silently drop
     * both, since this app never enables
     * `preventSilentlyDiscardingAttributes()`. `role` is only meaningful
     * once `is_admin` is true; cleared otherwise rather than left stale.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new User;
        $record->fill(Arr::except($data, ['is_admin', 'role']));
        $record->forceFill([
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'role' => ($data['is_admin'] ?? false) ? ($data['role'] ?? null) : null,
        ]);
        $record->save();
        ActivityLogger::record(Auth::user(), 'user.created', $record);

        return $record;
    }
}
