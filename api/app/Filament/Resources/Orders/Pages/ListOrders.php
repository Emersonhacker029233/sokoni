<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => CsvExporter::stream(
                    'orders-'.now()->format('Y-m-d-His').'.csv',
                    ['Code', 'Buyer', 'Seller', 'Total (TZS)', 'Status', 'Payment method', 'Placed'],
                    CsvExporter::cursorRows(
                        $this->getFilteredSortedTableQuery()->with(['buyer', 'seller'])->cursor(),
                        fn ($order) => [
                            $order->code,
                            $order->buyer?->name,
                            $order->seller?->shop_name,
                            $order->total,
                            $order->status,
                            $order->payment_method,
                            $order->created_at?->toDateTimeString(),
                        ],
                    ),
                )),
        ];
    }
}
