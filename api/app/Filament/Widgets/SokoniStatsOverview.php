<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "Stat cards showing a trend against the previous period, not just a
 * total" (CLAUDE.md admin rebuild, Section 5). Trend windows are the
 * trailing 30 days vs. the 30 days before that — every count comes from a
 * real query against real timestamps, no invented metric. When the prior
 * period is genuinely empty (a real state on a young platform, not
 * missing data), the percentage math is undefined (division by zero) —
 * shown as "New" rather than a fabricated "+∞%"/"-100%".
 */
class SokoniStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $now = now();
        $periodStart = $now->copy()->subDays(30);
        $priorStart = $now->copy()->subDays(60);

        $pendingSellers = SellerProfile::query()->where('status', 'pending')->count();
        $pendingReports = Report::query()->where('status', 'pending')->count();

        return [
            Stat::make('Users', number_format(User::query()->count()))
                ->description($this->trendDescription(
                    User::query()->whereBetween('created_at', [$periodStart, $now])->count(),
                    User::query()->whereBetween('created_at', [$priorStart, $periodStart])->count(),
                    'new in the last 30 days',
                )),

            Stat::make('Verified sellers', number_format(SellerProfile::query()->verified()->count()))
                ->description($this->trendDescription(
                    SellerProfile::query()->verified()->whereBetween('verified_at', [$periodStart, $now])->count(),
                    SellerProfile::query()->verified()->whereBetween('verified_at', [$priorStart, $periodStart])->count(),
                    'newly verified in the last 30 days',
                )),

            Stat::make('Pending verifications', $pendingSellers)
                ->description('Sellers awaiting review — see Seller Verification')
                ->color($pendingSellers > 0 ? 'warning' : 'success'),

            Stat::make('Pending reports', $pendingReports)
                ->description('Moderation queue')
                ->color($pendingReports > 0 ? 'danger' : 'success'),

            Stat::make('Live products', number_format(Product::query()->visible()->count()))
                ->description($this->trendDescription(
                    Product::query()->whereBetween('created_at', [$periodStart, $now])->count(),
                    Product::query()->whereBetween('created_at', [$priorStart, $periodStart])->count(),
                    'listed in the last 30 days',
                )),

            Stat::make('Orders', number_format(Order::query()->count()))
                ->description($this->trendDescription(
                    Order::query()->whereBetween('created_at', [$periodStart, $now])->count(),
                    Order::query()->whereBetween('created_at', [$priorStart, $periodStart])->count(),
                    'placed in the last 30 days',
                )),

            Stat::make(
                'Revenue (completed, 30d)',
                'TSh '.number_format((float) Order::query()->where('status', 'completed')->whereBetween('completed_at', [$periodStart, $now])->sum('total')),
            )->description($this->trendDescription(
                (float) Order::query()->where('status', 'completed')->whereBetween('completed_at', [$periodStart, $now])->sum('total'),
                (float) Order::query()->where('status', 'completed')->whereBetween('completed_at', [$priorStart, $periodStart])->sum('total'),
                'vs. the previous 30 days',
                isCurrency: true,
            )),
        ];
    }

    private function trendDescription(float $current, float $previous, string $label, bool $isCurrency = false): string
    {
        if ($previous <= 0.0) {
            return $current > 0 ? "New — {$label}" : "0 {$label}";
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';
        $currentDisplay = $isCurrency ? 'TSh '.number_format($current) : number_format($current);

        return "{$currentDisplay} {$label} ({$sign}".round($change).'% vs. prior 30 days)';
    }
}
