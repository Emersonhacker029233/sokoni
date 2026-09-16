<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'image_path', 'link_url', 'position', 'sort_order', 'starts_at', 'ends_at', 'is_active'])]
class Banner extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Active flag on, and — if a schedule is set at all — currently within it. A banner with no dates is always-on while active. */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeForPosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position)->orderBy('sort_order');
    }

    /** Called once per render, server-side — not client-side pixel tracking, which the analytics-free stack this app runs on has no way to receive anyway. */
    public function recordImpression(): void
    {
        $this->increment('impressions_count');
    }

    public function recordClick(): void
    {
        $this->increment('clicks_count');
    }

    /**
     * Part 1 (client feedback, urgent): "an uploaded banner never
     * appears" turned out, for at least some banners, to be a schedule
     * or is_active state the admin table never surfaced — a banner row
     * existing with a real uploaded file looks identical to one that's
     * simply not live yet, unless something says so plainly. Mirrors
     * scopeLive()'s own exact rules rather than restating them, so the
     * two can never silently drift apart.
     */
    public function liveStatusLabel(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'Scheduled — starts '.$this->starts_at->format('j M Y, H:i');
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'Expired — ended '.$this->ends_at->format('j M Y, H:i');
        }

        return 'Live now';
    }
}
