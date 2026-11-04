<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $modelClass = StoreReportRequest::REPORTABLE_TYPES[$request->string('reportable_type')->toString()];

        if (! $modelClass::query()->whereKey($request->integer('reportable_id'))->exists()) {
            throw ValidationException::withMessages(['reportable_id' => 'Not found.']);
        }

        // Reporting the same thing again while the first report is still
        // pending changes nothing for the moderators, so it doesn't file
        // (and alert) a second time. The reporter gets the same answer.
        Report::query()->firstOrCreate(
            [
                'reporter_id' => $request->user()->id,
                'reportable_type' => $modelClass,
                'reportable_id' => $request->integer('reportable_id'),
                'status' => 'pending',
            ],
            [
                'reason' => $request->string('reason')->toString(),
                'note' => $request->string('note')->toString() ?: null,
            ],
        );

        return response()->json(['message' => 'Report submitted for review.'], 201);
    }
}
