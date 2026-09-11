<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * D1 (tester feedback): no `DeleteAction` here on purpose — Filament's
 * generic one is a bare `$record->delete()`, with no reason, no cascade
 * to the user's shop/products, and no activity-log entry, entirely
 * bypassing everything `UsersTable`'s own custom `delete` action does.
 * Deletion stays a single path: the table row action (and its bulk
 * equivalent), both already reason-required, cascade-aware, and logged.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * See CreateUser's own handleRecordCreation() docblock — same reason:
     * `is_admin`/`role` are outside `#[Fillable]` on purpose, so the
     * default mass-assignment save would silently drop them.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $record->fill(Arr::except($data, ['is_admin', 'role']));
        $record->forceFill([
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'role' => ($data['is_admin'] ?? false) ? ($data['role'] ?? null) : null,
        ]);
        $record->save();
        ActivityLogger::record(Auth::user(), 'user.updated', $record);

        return $record;
    }
}
