<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * "Every admin action recorded with who, what, when and why — without an
 * audit trail there's no answer when a seller disputes it" (CLAUDE.md
 * admin rebuild, Section 6). Called directly from every moderation/
 * verification/ban/cancel action across the panel, not queued or batched
 * — these are low-volume admin actions, not request-hot-path traffic, so
 * a synchronous insert is simpler and can't be lost to a failed queue job.
 */
class ActivityLogger
{
    public static function record(User $causer, string $action, ?Model $subject = null, ?string $reason = null, array $meta = []): ActivityLog
    {
        // forceCreate, not create() — ActivityLog declares no #[Fillable]
        // at all (see its own docblock), since nothing about this record
        // should ever be settable from a request; this array is built
        // entirely from this method's own typed parameters, never from
        // user input.
        return ActivityLog::forceCreate([
            'causer_id' => $causer->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }
}
