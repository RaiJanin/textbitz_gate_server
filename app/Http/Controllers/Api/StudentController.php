<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TapEvent;
use App\Services\Attendance\AlertBuilder;
use App\Services\Attendance\DayRecordBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StudentController extends Controller
{
    /**
     * Today's timeline + derived presence state.
     */
    public function status(Request $request, Student $student, DayRecordBuilder $days): JsonResponse
    {
        $student->loadMissing('school');
        $tz = $student->school->timezone;
        $today = Carbon::now($tz)->startOfDay();

        $record = $days->forDay($student, $today);

        $lastTap = $student->tapEvents()->forDay($today)->orderByDesc('tapped_at')->orderByDesc('id')->first();

        $presence = match (true) {
            $lastTap === null => 'not_arrived',
            $lastTap->direction === TapEvent::DIRECTION_IN => 'at_school',
            default => 'left',
        };

        return response()->json([
            'date' => $record['date'],
            'presence' => $presence,
            'first_in' => $record['first_in'],
            'last_out' => $record['last_out'],
            'on_time' => $record['state'] === DayRecordBuilder::STATE_ON_TIME,
            'is_late' => $record['state'] === DayRecordBuilder::STATE_LATE,
            'state' => $record['state'],
            'timeline' => $record['taps'],
        ]);
    }

    /**
     * One record per day for a calendar month.
     */
    public function history(Request $request, Student $student, DayRecordBuilder $days): JsonResponse
    {
        $student->loadMissing('school');
        $tz = $student->school->timezone;

        $month = $request->query('month')
            ? Carbon::createFromFormat('Y-m', (string) $request->query('month'), $tz)
            : Carbon::now($tz);

        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        return response()->json([
            'month' => $from->format('Y-m'),
            'days' => $days->forRange($student, $from, $to)->all(),
        ]);
    }

    public function alerts(Request $request, Student $student, AlertBuilder $alerts): JsonResponse
    {
        $student->loadMissing('school');

        $perPage = 15;
        $page = max(1, (int) $request->query('page', 1));

        $all = $alerts->forStudent($student, days: 90);
        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'alerts' => $slice,
            'page' => $page,
            'has_more' => ($page * $perPage) < count($all),
        ]);
    }
}
