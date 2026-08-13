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

        Report::query()->create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $request->integer('reportable_id'),
            'reason' => $request->string('reason'),
            'note' => $request->string('note') ?: null,
        ]);

        return response()->json(['message' => 'Report submitted for review.'], 201);
    }
}
