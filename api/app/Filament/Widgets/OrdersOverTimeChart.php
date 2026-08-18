<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

/** "Orders over time — line chart, 30 days" (CLAUDE.md admin rebuild, Section 5). Real per-day counts, days with zero orders included so the line doesn't lie by omission. */
class OrdersOverTimeChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Orders over time (30 days)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $counts = Order::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d M');
            $data[] = (int) ($counts[$date] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data,
                    'borderColor' => '#FAC902',
                    'backgroundColor' => 'rgba(250, 201, 2, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
