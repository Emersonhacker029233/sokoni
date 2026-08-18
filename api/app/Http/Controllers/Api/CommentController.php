<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommentStoreRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    /** Root comments, newest first, each with its one level of replies eager loaded. */
    public function index(Product $product): AnonymousResourceCollection
    {
        $comments = $product->comments()
            ->where('is_hidden', false)
            ->with(['user', 'product', 'replies' => fn ($query) => $query->where('is_hidden', false), 'replies.user', 'replies.product'])
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(CommentStoreRequest $request, Product $product): CommentResource
    {
        $comment = $product->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $request->input('parent_id'),
            'body' => $request->string('body'),
        ]);

        return new CommentResource($comment->load(['user', 'product']));
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
