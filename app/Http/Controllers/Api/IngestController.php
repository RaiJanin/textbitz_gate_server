<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TapIngestRequest;
use App\Models\Gate;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\RecordTap;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class IngestController extends Controller
{
    public function tap(TapIngestRequest $request, RecordTap $recordTap): JsonResponse
    {
        /** @var School $school */
        $school = $request->attributes->get('ingest_school');

        $student = Student::where('school_id', $school->id)
            ->where('rfid_uid', $request->validated('rfid_uid'))
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Unknown RFID UID for this school.',
                'error' => 'unknown_uid',
            ], 422);
        }

        $gate = Gate::where('school_id', $school->id)
            ->find($request->validated('gate_id'));

        if (! $gate) {
            return response()->json([
                'message' => 'Unknown gate for this school.',
                'error' => 'unknown_gate',
            ], 422);
        }

        $tappedAt = $request->filled('timestamp')
            ? Carbon::parse($request->validated('timestamp'))
            : Carbon::now();

        $tap = $recordTap->record($student, $gate, $tappedAt, source: 'ingest');

        return response()->json([
            'tap' => [
                'id' => $tap->id,
                'student_id' => $tap->student_id,
                'gate_id' => $tap->gate_id,
                'direction' => $tap->direction,
                'tapped_at' => $tap->tapped_at->toIso8601String(),
                'is_late' => $tap->is_late,
            ],
        ], 201);
    }
}
