<?php

namespace App\Models;

use App\Models\Concerns\HidesBlockedAuthors;
use App\Observers\ReviewObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'seller_id', 'buyer_id', 'rating', 'comment'])]
#[ObservedBy(ReviewObserver::class)]
class Review extends Model
{
    use HasFactory, HidesBlockedAuthors;

    /** Shown to the public: not hidden by a moderator, and the reviewer isn't banned or suspended. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('is_hidden'), false)
            ->whereDoesntHave('buyer', fn (Builder $author) => $author->banned());
    }

    protected function withoutAuthors(Builder $query, array $userIds): Builder
    {
        return $query->whereNotIn($this->qualifyColumn('buyer_id'), $userIds);
    }

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function hasReply(): bool
    {
        return $this->reply !== null;
    }
}
