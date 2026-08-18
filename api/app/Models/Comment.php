<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A comment on a product's "For You" feed card (CLAUDE.md Part 3). One
 * level of replies only — `parent_id` self-references this same table, and
 * "a reply can't itself have replies" is enforced in `CommentStoreRequest`
 * rather than the schema, since it's a product rule, not a data-integrity
 * constraint the database needs to guarantee.
 */
#[Fillable(['product_id', 'user_id', 'parent_id', 'body'])]
class Comment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }

    /** True when the commenter is the product's own seller — badged client-side. */
    public function isFromSeller(): bool
    {
        return $this->user->sellerProfileId() === $this->product->seller_id;
    }
}
