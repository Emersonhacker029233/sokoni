<?php

namespace App\Filament\Resources\SellerProfiles\Pages;

use App\Filament\Resources\SellerProfiles\SellerProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListSellerProfiles extends ListRecords
{
    protected static string $resource = SellerProfileResource::class;
}
