<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedItemResource;
use App\Services\Feed\FeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function __construct(private readonly FeedService $feed) {}

    /**
     * The "For You" feed (CLAUDE.md Part 3) — public like every other
     * discovery endpoint (browsing works without an account), personalised
     * with followed shops when a token is present. `lat`/`lng` are
     * optional, same fallback as `GET /products`: no location just means
     * the non-followed tier sorts newest-first instead of nearest-first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $lat = $request->filled('lat') ? $request->float('lat') : null;
        $lng = $request->filled('lng') ? $request->float('lng') : null;
        $page = (int) ($request->integer('page') ?: 1);

        $paginated = $this->feed->paginate($request->user(), $lat, $lng, $page);

        return FeedItemResource::collection($paginated);
    }
}
