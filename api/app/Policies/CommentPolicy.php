<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /** The comment's own author, or the product's seller moderating their own feed card. */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id
            || $user->sellerProfileId() === $comment->product->seller_id;
    }
}
