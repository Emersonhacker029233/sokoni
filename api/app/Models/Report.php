<?php

namespace App\Models;

use App\Observers\ReportObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'note'])]
#[ObservedBy(ReportObserver::class)]
class Report extends Model
{
    use HasFactory;

    /** Reports upheld against the same reportable at/above this count auto-hide it. */
    public const AUTO_HIDE_THRESHOLD = 3;

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

    /**
     * The user "behind" the reported content — who a warn/suspend/ban
     * action (CLAUDE.md feature 11) actually applies to. Each reportable
     * type reaches its owning user through a different path.
     */
    public function offendingUser(): ?User
    {
        return match (true) {
            $this->reportable instanceof Product => $this->reportable->seller?->user,
            $this->reportable instanceof SellerProfile => $this->reportable->user,
            $this->reportable instanceof Message => $this->reportable->sender,
            default => null,
        };
    }
}
