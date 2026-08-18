<?php

namespace App\Filament\Widgets;

use App\Models\SellerProfile;
use App\Models\User;
use Filament\Widgets\ChartWidget;

/** "New users and new sellers over time" (CLAUDE.md admin rebuild, Section 5). Two real per-day series, 30 days, zero-filled. */
class NewUsersAndSellersChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'New users & sellers (30 days)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $userCounts = User::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $sellerCounts = SellerProfile::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $users = [];
        $sellers = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d M');
            $users[] = (int) ($userCounts[$date] ?? 0);
            $sellers[] = (int) ($sellerCounts[$date] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'New users',
                    'data' => $users,
                    'borderColor' => '#0A0A0A',
                    'backgroundColor' => 'rgba(10, 10, 10, 0.08)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'New sellers',
                    'data' => $sellers,
                    'borderColor' => '#12A150',
                    'backgroundColor' => 'rgba(18, 161, 80, 0.12)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
