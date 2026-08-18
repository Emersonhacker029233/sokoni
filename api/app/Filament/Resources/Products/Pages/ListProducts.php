<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => CsvExporter::stream(
                    'products-'.now()->format('Y-m-d-His').'.csv',
                    ['ID', 'Title', 'Shop', 'Category', 'Price (TZS)', 'Stock', 'Status', 'Sponsored', 'Views', 'Listed'],
                    CsvExporter::cursorRows(
                        $this->getFilteredSortedTableQuery()->with(['seller', 'category'])->cursor(),
                        fn ($product) => [
                            $product->id,
                            $product->title,
                            $product->seller?->shop_name,
                            $product->category?->name_en,
                            $product->price,
                            $product->stock,
                            $product->is_hidden ? 'Hidden' : ($product->is_active ? 'Live' : 'Inactive'),
                            ($product->is_sponsored && $product->sponsored_until?->isFuture()) ? 'Yes' : 'No',
                            $product->views,
                            $product->created_at?->toDateTimeString(),
                        ],
                    ),
                )),
        ];
    }
}
