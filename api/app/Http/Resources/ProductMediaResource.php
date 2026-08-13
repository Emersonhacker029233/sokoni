<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductMedia */
class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'path' => $this->path,
            'thumb_path' => $this->thumb_path,
            'card_path' => $this->card_path,
            'duration' => $this->duration,
            'sort' => $this->sort,
        ];
    }
}
