<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ActivityLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * D1: no `DeleteAction` here, same reasoning as EditUser — deletion stays
 * the single, reason-required, activity-logged path already built into
 * ProductsTable's own `delete` action (and its bulk equivalent), not a
 * second, bare, unreasoned one bolted onto this page's header.
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        ActivityLogger::record(Auth::user(), 'product.updated', $record);

        return $record;
    }
}
