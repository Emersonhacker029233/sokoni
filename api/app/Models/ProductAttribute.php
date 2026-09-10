<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'key', 'value'])]
class ProductAttribute extends Model
{
    use HasFactory;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
