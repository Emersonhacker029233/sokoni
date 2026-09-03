<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * The website had no report mechanism at all — not a missing-feedback gap
 * like reviews, but a genuinely absent CLAUDE.md feature 11 requirement
 * ("Report button on every product, shop and message") that only ever
 * existed in the app (tester feedback A3's audit). Reuses the exact same
 * `StoreReportRequest`/type map the API uses, so a report filed here
 * reaches the identical moderation queue.
 */
class ReportController extends Controller
{
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $modelClass = StoreReportRequest::REPORTABLE_TYPES[$request->string('reportable_type')->toString()];

        if (! $modelClass::query()->whereKey($request->integer('reportable_id'))->exists()) {
            throw ValidationException::withMessages(['reportable_id' => 'Not found.']);
        }

        Report::query()->create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $request->integer('reportable_id'),
            'reason' => $request->string('reason'),
            'note' => $request->string('note') ?: null,
        ]);

        return back()->with('status', __('site.report_submitted'));
    }
}
