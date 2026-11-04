<?php

namespace App\Filament\Resources\Reports;

use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Push\PushNotifier;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * What a moderator can do with a report (Apple Guideline 1.2: "act on
 * objectionable content reports within 24 hours by removing the content
 * and ejecting the user who provided the offending content"). Shared by
 * the Reports list and the report's own page.
 *
 * Every action that does something about the content or its author also
 * resolves a pending report as upheld, with who and when, so the 24-hour
 * clock stops at the moment someone acted.
 */
final class ReportActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [
            self::removeAndBan(),
            self::hide(),
            self::unhide(),
            self::uphold(),
            self::dismiss(),
            self::warn(),
            self::suspend(),
            self::ban(),
            self::removeLogo(),
            self::clearBio(),
            self::removeAvatar(),
        ];
    }

    /** One click for the usual case: the content goes, and so does the person who posted it. */
    public static function removeAndBan(): Action
    {
        return Action::make('removeAndBan')
            ->label('Remove content and ban user')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (Report $record) => self::bannable($record))
            ->requiresConfirmation()
            ->modalDescription('Hides this content, bans its author permanently, signs them out everywhere and hides everything else they posted. Unbanning later restores their other content.')
            ->schema([Textarea::make('reason')->label('Reason (sent to the user)')->required()])
            ->action(function (Report $record, array $data) {
                if ($record->canHideContent()) {
                    $record->reportable->forceFill(['is_hidden' => true])->save();
                    ActivityLogger::record(auth()->user(), 'content.hidden', $record->reportable, 'Reported: '.$record->reasonLabel());
                }
                self::banUser($record->offendingUser(), $data['reason'], null);
                self::resolve($record, 'Content removed and user banned: '.$data['reason']);
                Notification::make()->title('Content removed and user banned')->success()->send();
            });
    }

    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('Hide content')
            ->icon('heroicon-o-eye-slash')
            ->color('warning')
            ->visible(fn (Report $record) => $record->canHideContent() && ! $record->reportable->is_hidden)
            ->requiresConfirmation()
            ->action(function (Report $record) {
                $record->reportable->forceFill(['is_hidden' => true])->save();
                ActivityLogger::record(auth()->user(), 'content.hidden', $record->reportable, 'Reported: '.$record->reasonLabel());
                self::resolve($record, 'Content hidden.');
                Notification::make()->title('Content hidden')->success()->send();
            });
    }

    public static function unhide(): Action
    {
        return Action::make('unhide')
            ->label('Unhide content')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (Report $record) => $record->canHideContent() && $record->reportable->is_hidden)
            ->requiresConfirmation()
            ->action(function (Report $record) {
                $record->reportable->forceFill(['is_hidden' => false])->save();
                ActivityLogger::record(auth()->user(), 'content.unhidden', $record->reportable);
                Notification::make()->title('Content visible again')->send();
            });
    }

    public static function uphold(): Action
    {
        return Action::make('uphold')
            ->label('Uphold')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(fn (Report $record) => $record->status === 'pending')
            ->requiresConfirmation()
            ->action(function (Report $record) {
                self::resolve($record, 'Upheld by admin review.');
                ActivityLogger::record(auth()->user(), 'report.upheld', $record);
                Notification::make()->title('Report upheld')->success()->send();
            });
    }

    public static function dismiss(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->visible(fn (Report $record) => $record->status === 'pending')
            ->action(function (Report $record) {
                $record->forceFill([
                    'status' => 'dismissed',
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                    'resolution' => 'Dismissed — no action taken.',
                ])->save();
                ActivityLogger::record(auth()->user(), 'report.dismissed', $record);
                Notification::make()->title('Report dismissed')->send();
            });
    }

    public static function warn(): Action
    {
        return Action::make('warn')
            ->label('Warn user')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('warning')
            ->visible(fn (Report $record) => $record->offendingUser() !== null)
            ->schema([Textarea::make('reason')->required()])
            ->action(function (Report $record, array $data) {
                $user = $record->offendingUser();
                app(PushNotifier::class)->notify($user, 'Warning from Sokoni', $data['reason']);
                ActivityLogger::record(auth()->user(), 'user.warned', $user, $data['reason']);
                self::resolve($record, 'User warned: '.$data['reason']);
                Notification::make()->title('Warning sent')->success()->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspend user')
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->visible(fn (Report $record) => self::bannable($record))
            ->schema([
                TextInput::make('days')->numeric()->minValue(1)->required()->default(7),
                Textarea::make('reason')->required(),
            ])
            ->action(function (Report $record, array $data) {
                self::banUser($record->offendingUser(), $data['reason'], (int) $data['days']);
                self::resolve($record, "User suspended for {$data['days']} days: ".$data['reason']);
                Notification::make()->title('User suspended')->success()->send();
            });
    }

    public static function ban(): Action
    {
        return Action::make('ban')
            ->label('Ban user')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Report $record) => self::bannable($record))
            ->schema([Textarea::make('reason')->required()])
            ->requiresConfirmation()
            ->action(function (Report $record, array $data) {
                self::banUser($record->offendingUser(), $data['reason'], null);
                self::resolve($record, 'User banned: '.$data['reason']);
                Notification::make()->title('User banned')->success()->send();
            });
    }

    public static function removeLogo(): Action
    {
        return Action::make('removeLogo')
            ->label('Remove shop logo')
            ->icon('heroicon-o-photo')
            ->color('warning')
            ->visible(fn (Report $record) => $record->reportable instanceof SellerProfile && $record->reportable->logo !== null)
            ->requiresConfirmation()
            ->action(function (Report $record) {
                $record->reportable->forceFill(['logo' => null])->save();
                ActivityLogger::record(auth()->user(), 'shop.logo_removed', $record->reportable, 'Reported: '.$record->reasonLabel());
                self::resolve($record, 'Shop logo removed.');
                Notification::make()->title('Logo removed')->success()->send();
            });
    }

    public static function clearBio(): Action
    {
        return Action::make('clearBio')
            ->label('Clear shop bio')
            ->icon('heroicon-o-document-minus')
            ->color('warning')
            ->visible(fn (Report $record) => $record->reportable instanceof SellerProfile
                && ($record->reportable->bio !== null || $record->reportable->bio_sw !== null))
            ->requiresConfirmation()
            ->action(function (Report $record) {
                $record->reportable->forceFill(['bio' => null, 'bio_sw' => null])->save();
                ActivityLogger::record(auth()->user(), 'shop.bio_cleared', $record->reportable, 'Reported: '.$record->reasonLabel());
                self::resolve($record, 'Shop bio cleared.');
                Notification::make()->title('Bio cleared')->success()->send();
            });
    }

    public static function removeAvatar(): Action
    {
        return Action::make('removeAvatar')
            ->label('Remove profile photo')
            ->icon('heroicon-o-user-circle')
            ->color('warning')
            ->visible(fn (Report $record) => $record->offendingUser()?->avatar !== null)
            ->requiresConfirmation()
            ->action(function (Report $record) {
                $user = $record->offendingUser();
                $user->forceFill(['avatar' => null])->save();
                ActivityLogger::record(auth()->user(), 'user.avatar_removed', $user, 'Reported: '.$record->reasonLabel());
                self::resolve($record, 'Profile photo removed.');
                Notification::make()->title('Profile photo removed')->success()->send();
            });
    }

    /** Admin accounts are never banned from a report: that is a decision for the Users screen. */
    private static function bannable(Report $record): bool
    {
        $user = $record->offendingUser();

        return $user !== null && ! $user->is_admin && ! $user->isBanned();
    }

    /**
     * Bans for good (`$days` null) or suspends, signs the user out of every
     * device, and tells them why. Their shop, products, posts, comments and
     * reviews drop out of every public list while the ban lasts (the
     * ban-aware `listed()`/`publiclyVisible()` scopes).
     */
    private static function banUser(User $user, string $reason, ?int $days): void
    {
        $user->forceFill([
            'banned_at' => now(),
            'banned_until' => $days === null ? null : now()->addDays($days),
            'ban_reason' => $reason,
        ])->save();
        $user->tokens()->delete();

        app(PushNotifier::class)->notify($user, $days === null ? 'Account banned' : 'Account suspended', $reason);
        ActivityLogger::record(
            auth()->user(),
            $days === null ? 'user.banned' : 'user.suspended',
            $user,
            $reason,
            $days === null ? [] : ['days' => $days],
        );
    }

    private static function resolve(Report $record, string $resolution): void
    {
        if ($record->status !== 'pending') {
            return;
        }

        $record->forceFill([
            'status' => 'upheld',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution' => $resolution,
        ])->save();
    }
}
