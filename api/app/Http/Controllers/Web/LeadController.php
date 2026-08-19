<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/** "Logs the reveal as a lead" (CLAUDE.md website Section 3) — fired via a small fetch() alongside the tel: link, never blocking it. */
class LeadController extends Controller
{
    public function revealCall(Product $product): JsonResponse
    {
        Lead::create([
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'user_id' => Auth::id(),
            'type' => 'call',
        ]);

        return response()->json(['ok' => true]);
    }
}
