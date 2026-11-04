<?php

namespace App\Models;

use App\Observers\ReportObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'note'])]
#[ObservedBy(ReportObserver::class)]
class Report extends Model
{
    use HasFactory;

    /** Apple Guideline 1.2: every report is acted on within this many hours. */
    public const RESPONSE_HOURS = 24;

    /** Client-facing type names (the API's `reportable_type`) mapped to model classes. */
    public const TYPES = [
        'product' => Product::class,
        'shop' => SellerProfile::class,
        'message' => Message::class,
        'comment' => Comment::class,
        'review' => Review::class,
        'showcase' => Showcase::class,
        'update' => Update::class,
        'offer' => Offer::class,
        'user' => User::class,
    ];

    /**
     * Reason codes the app sends since 1.0 (9), with the label admins see.
     * Older builds send their own translated label instead (for example
     * "Spam" or "Si maudhui ya biashara"); those are stored and shown as is.
     */
    public const REASONS = [
        'abusive' => 'Offensive or abusive',
        'harassment' => 'Harassment or hate',
        'sexual_violent' => 'Sexual or violent content',
        'scam' => 'Scam or fraud',
        'prohibited' => 'Prohibited item',
        'misleading' => 'Misleading listing',
        'not_business' => 'Not business content',
        'spam' => 'Spam',
        'other' => 'Something else',
        'blocked' => 'Blocked by a user',
    ];

    protected function casts(): array
    {
        return [
            'alerted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** Pending reports that have waited at least $hours. */
    public function scopeWaitingAtLeast(Builder $query, int $hours): Builder
    {
        return $query->pending()->where('created_at', '<=', now()->subHours($hours));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->waitingAtLeast(self::RESPONSE_HOURS);
    }

    /** Every report about $user or about anything they posted: their profile, shop, listings, posts, comments, reviews and messages. */
    public function scopeAgainstUser(Builder $query, User $user): Builder
    {
        $shopIds = SellerProfile::withTrashed()->where('user_id', $user->id)->pluck('id');
        $targets = [
            User::class => [$user->id],
            SellerProfile::class => $shopIds,
            Product::class => Product::withTrashed()->whereIn('seller_id', $shopIds)->pluck('id'),
            Showcase::class => Showcase::query()->whereIn('seller_id', $shopIds)->pluck('id'),
            Update::class => Update::query()->whereIn('seller_id', $shopIds)->pluck('id'),
            Offer::class => Offer::query()->whereIn('seller_id', $shopIds)->pluck('id'),
            Comment::class => Comment::query()->where('user_id', $user->id)->pluck('id'),
            Review::class => Review::query()->where('buyer_id', $user->id)->pluck('id'),
            Message::class => Message::query()->where('sender_id', $user->id)->pluck('id'),
        ];

        return $query->where(function (Builder $q) use ($targets) {
            foreach ($targets as $type => $ids) {
                $q->orWhere(fn (Builder $one) => $one->where('reportable_type', $type)->whereIn('reportable_id', $ids));
            }
        });
    }

    public function dueAt(): Carbon
    {
        return $this->created_at->copy()->addHours(self::RESPONSE_HOURS);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->dueAt()->isPast();
    }

    /** "5h left", "Overdue by 3h", or the status once resolved — the Reports list's Due column. */
    public function dueLabel(): string
    {
        if ($this->status !== 'pending') {
            return ucfirst($this->status);
        }

        $minutes = (int) now()->diffInMinutes($this->dueAt(), false);
        $hours = intdiv(abs($minutes), 60);
        $text = $hours >= 1 ? "{$hours}h" : abs($minutes).'m';

        return $minutes >= 0 ? "{$text} left" : "Overdue by {$text}";
    }

    public function dueColor(): string
    {
        if ($this->status !== 'pending') {
            return 'gray';
        }
        if ($this->isOverdue()) {
            return 'danger';
        }

        return now()->diffInHours($this->dueAt(), false) < 6 ? 'warning' : 'success';
    }

    public function typeName(): string
    {
        return array_search($this->reportable_type, self::TYPES, true) ?: class_basename($this->reportable_type);
    }

    public function reasonLabel(): string
    {
        $reason = (string) $this->reason;

        return self::REASONS[$reason] ?? $reason;
    }

    /**
     * The user "behind" the reported content — who a warn/suspend/ban
     * action (CLAUDE.md feature 11) actually applies to. Each reportable
     * type reaches its owning user through a different path.
     */
    public function offendingUser(): ?User
    {
        $item = $this->reportable;

        return match (true) {
            $item instanceof Product => $item->seller?->user,
            $item instanceof SellerProfile => $item->user,
            $item instanceof Message => $item->sender,
            $item instanceof Comment => $item->user,
            $item instanceof Review => $item->buyer,
            $item instanceof Showcase, $item instanceof Update, $item instanceof Offer => $item->seller?->user,
            $item instanceof User => $item,
            default => null,
        };
    }

    /** True when the reported item has an `is_hidden` flag, so "Hide content" applies. */
    public function canHideContent(): bool
    {
        return $this->reportable instanceof Model
            && ! $this->reportable instanceof User
            && ! $this->reportable instanceof Offer
            && array_key_exists('is_hidden', $this->reportable->getAttributes());
    }

    /**
     * What a moderator needs to judge the report without hunting for it:
     * a title, the text, and any pictures or video.
     *
     * @return array{title: string, text: ?string, images: list<string>, video: ?string}
     */
    public function contentPreview(): array
    {
        $item = $this->reportable;
        $preview = fn (string $title, ?string $text = null, array $images = [], ?string $video = null) => [
            'title' => $title,
            'text' => $text !== null && trim($text) !== '' ? $text : null,
            'images' => array_values(array_filter($images, fn ($url) => is_string($url) && $url !== '')),
            'video' => $video,
        ];

        return match (true) {
            $item === null => $preview('This content no longer exists.'),
            $item instanceof Product => $preview(
                $item->title,
                $item->description,
                $item->media->where('type', 'image')->map(fn ($m) => $m->card_path ?? $m->path)->take(4)->all(),
                $item->media->firstWhere('type', 'video')?->path,
            ),
            $item instanceof SellerProfile => $preview(
                $item->shop_name.' (@'.$item->handle.')',
                $item->bio,
                [$item->logo],
            ),
            $item instanceof Message => $preview('Chat message', $item->body, [$item->attachment]),
            $item instanceof Comment => $preview('Comment on "'.($item->product?->title ?? 'a product').'"', $item->body),
            $item instanceof Review => $preview(
                str_repeat('★', (int) $item->rating).' review',
                trim(($item->comment ?? '').($item->reply ? "\n\nShop's reply: ".$item->reply : '')),
            ),
            $item instanceof Showcase => $preview('Showcase video', $item->caption, [$item->thumb_path], $item->video_path),
            $item instanceof Update => $preview(
                'Update ('.$item->type.')',
                $item->caption,
                [$item->type === 'image' ? $item->media_path : $item->thumb_path],
                $item->type === 'video' ? $item->media_path : null,
            ),
            $item instanceof Offer => $preview(
                'Offer on "'.($item->product?->title ?? 'a product').'"',
                $item->discount_type === 'percent'
                    ? rtrim(rtrim((string) $item->discount_value, '0'), '.').'% off'
                    : 'Sale price TSh '.number_format((float) $item->discount_value),
            ),
            $item instanceof User => $preview(
                'User profile: '.$item->name.($item->username ? ' (@'.$item->username.')' : ''),
                null,
                [$item->avatar],
            ),
            default => $preview(class_basename($item)),
        };
    }

    /** One line for emails and the admin bell: "Comment — Offensive or abusive: “text…”". */
    public function summaryLine(): string
    {
        $preview = $this->contentPreview();
        $text = $preview['text'] ?? $preview['title'];

        return ucfirst($this->typeName()).' — '.$this->reasonLabel().': “'.Str::limit(str_replace("\n", ' ', $text), 120).'”';
    }
}
