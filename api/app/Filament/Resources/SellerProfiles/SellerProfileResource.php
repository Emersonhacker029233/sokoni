<?php

namespace App\Filament\Resources\SellerProfiles;

use App\Filament\Resources\SellerProfiles\Pages\ListSellerProfiles;
use App\Filament\Resources\SellerProfiles\Pages\ViewSellerProfile;
use App\Filament\Resources\SellerProfiles\Schemas\SellerProfileInfolist;
use App\Filament\Resources\SellerProfiles\Tables\SellerProfilesTable;
use App\Models\SellerProfile;
use App\Services\SellerVerificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The seller verification queue (CLAUDE.md feature 9). No create/edit
 * pages: sellers create and edit their own profile through the app's
 * onboarding wizard — this resource is purely the admin's review-and-
 * decide surface (list + a NIDA number/map view, verify/reject actions
 * on the table itself). B1/B2 (tester feedback): no NIDA photo or
 * licence — the typed NIDA number alone is the basis of verification.
 */
class SellerProfileResource extends Resource
{
    /**
     * Shared with {@see ViewSellerProfile}'s header actions, not just the
     * table row action — a real bug (not a guess: reproduced directly via
     * `Livewire::test(ViewSellerProfile::class)->instance()
     * ->getCachedHeaderActions()`, which came back empty) was that the
     * *only* place these actions existed was the list table, which shows
     * no NIDA photo, no licence, no map — none of the evidence a reviewer
     * actually needs to decide. The view page (`SellerProfileInfolist`,
     * literally docblocked as "the seller verification queue's core
     * screen") had no way to act on what it was showing. The backend
     * logic itself (forceFill/save, the policy) was never the problem —
     * confirmed by invoking the table action directly via Livewire before
     * touching any code, which worked with no error.
     */
    public static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (SellerProfile $record) => $record->status !== 'verified')
            ->requiresConfirmation()
            ->action(function (SellerProfile $record) {
                SellerVerificationService::verify($record, auth()->user());
                Notification::make()->title('Seller verified')->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (SellerProfile $record) => $record->status !== 'rejected')
            ->schema([
                Textarea::make('reason')->label('Reason')->required(),
            ])
            ->action(function (SellerProfile $record, array $data) {
                SellerVerificationService::reject($record, auth()->user(), $data['reason']);
                Notification::make()->title('Seller rejected')->warning()->send();
            });
    }

    protected static ?string $model = SellerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Trust & Safety';

    protected static ?string $navigationLabel = 'Seller Verification';

    protected static ?string $modelLabel = 'seller verification';

    // See CategoryResource for why this matters.
    protected static ?string $recordTitleAttribute = 'shop_name';

    /** "Global search across products, shops, users and orders" (CLAUDE.md admin rebuild, Section 6) — "shops" means this resource, even though it's off the main nav (see below). */
    public static function getGloballySearchableAttributes(): array
    {
        return ['shop_name', 'handle'];
    }

    /**
     * Off the main nav — {@see \App\Filament\Pages\SellerVerificationQueue}
     * is the one "Seller Verification" nav item now (CLAUDE.md admin
     * rebuild, Section 4: "a dedicated queue page, not a filtered list").
     * This resource's routes stay live and unregistered-but-reachable so
     * Products/Media can still deep-link to a specific seller's full
     * record regardless of status (verified/rejected sellers included,
     * which the queue page deliberately never shows).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return SellerProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SellerProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellerProfiles::route('/'),
            'view' => ViewSellerProfile::route('/{record}'),
        ];
    }
}
