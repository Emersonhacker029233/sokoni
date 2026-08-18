<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** "Revenue by category — bar chart" (CLAUDE.md admin rebuild, Section 5). Sums completed-order line items joined through to each product's category — lifetime, not windowed, since a 30-day slice would hide categories that only sell occasionally. */
class RevenueByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Revenue by category (completed orders, lifetime)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', 'completed')
            ->selectRaw('COALESCE(categories.name_en, ?) as category, SUM(order_items.price_snapshot * order_items.qty) as revenue', ['Uncategorized'])
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (TZS)',
                    'data' => $rows->pluck('revenue')->map(fn ($v) => (float) $v)->all(),
                    'backgroundColor' => '#FAC902',
                ],
            ],
            'labels' => $rows->pluck('category')->all(),
        ];
    }
}
