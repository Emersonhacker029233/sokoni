<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SokoniStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $pendingSellers = SellerProfile::query()->where('status', 'pending')->count();
        $pendingReports = Report::query()->where('status', 'pending')->count();
        $completedRevenue = Order::query()->where('status', 'completed')->sum('total');

        return [
            Stat::make('Users', User::query()->count())
                ->description(SellerProfile::query()->verified()->count().' verified sellers'),
            Stat::make('Pending verifications', $pendingSellers)
                ->description('Sellers awaiting review')
                ->color($pendingSellers > 0 ? 'warning' : 'success'),
            Stat::make('Pending reports', $pendingReports)
                ->description('Moderation queue')
                ->color($pendingReports > 0 ? 'danger' : 'success'),
            Stat::make('Live products', Product::query()->visible()->count()),
            Stat::make('Orders', Order::query()->count())
                ->description(Order::query()->where('status', 'pending')->count().' pending'),
            Stat::make('Revenue (completed orders)', 'TSh '.number_format((float) $completedRevenue)),
        ];
    }
}
