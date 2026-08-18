<?php

namespace App\Filament\Pages;

use App\Models\SellerProfile;
use App\Support\ActivityLogger;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "A dedicated queue page, not a filtered list" (CLAUDE.md admin rebuild,
 * Section 4) — the previous implementation was `SellerProfileResource`'s
 * table (a generic filtered DataTable an admin had to click into a
 * separate view page for the evidence, see Section 1's fix). This page
 * shows every pending applicant's NIDA photo, licence, map link and
 * business details inline, oldest submission first, with the decision
 * one click away for each — nothing to navigate into.
 *
 * `SellerProfileResource` still exists (kept off the main nav — see its
 * own `shouldRegisterNavigation()`) purely so other resources
 * (Products/Media) can still deep-link to a specific seller's full record
 * regardless of status; this page is the *only* verification workflow.
 */
class SellerVerificationQueue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Trust & Safety';

    protected static ?string $navigationLabel = 'Seller Verification';

    protected static ?string $slug = 'seller-verification';

    protected string $view = 'filament.pages.seller-verification-queue';

    /** @var array<int, string> */
    public array $rejectReasons = [];

    /** @var array<int, bool> */
    public array $rejecting = [];

    public function getTitle(): string
    {
        return 'Seller Verification';
    }

    /** Oldest submission first — a reviewer works the longest-waiting applicant down, not whichever loaded last. */
    public function getPending(): Collection
    {
        return SellerProfile::query()
            ->where('status', 'pending')
            ->with(['user', 'category'])
            ->oldest()
            ->get();
    }

    public function startReject(int $sellerId): void
    {
        $this->rejecting[$sellerId] = true;
    }

    public function cancelReject(int $sellerId): void
    {
        unset($this->rejecting[$sellerId], $this->rejectReasons[$sellerId]);
    }

    public function approve(int $sellerId): void
    {
        $seller = SellerProfile::findOrFail($sellerId);

        $seller->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();

        ActivityLogger::record(Auth::user(), 'seller.verified', $seller);

        Notification::make()->title($seller->shop_name.' verified')->success()->send();
    }

    public function reject(int $sellerId): void
    {
        $reason = trim($this->rejectReasons[$sellerId] ?? '');

        if ($reason === '') {
            Notification::make()->title('A rejection reason is required')->danger()->send();

            return;
        }

        $seller = SellerProfile::findOrFail($sellerId);

        $seller->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'verified_at' => null,
        ])->save();

        ActivityLogger::record(Auth::user(), 'seller.rejected', $seller, $reason);

        unset($this->rejecting[$sellerId], $this->rejectReasons[$sellerId]);

        Notification::make()->title($seller->shop_name.' rejected')->warning()->send();
    }
}
