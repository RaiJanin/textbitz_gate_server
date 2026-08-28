<?php

namespace App\Http\Controllers;

use App\Models\Gate;
use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use App\Services\Attendance\AlertBuilder;
use App\Services\Attendance\DayRecordBuilder;
use App\Services\Attendance\SimulationProfile;
use App\Services\Attendance\TurnstileSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Dev-only turnstile simulator control panel. Registered from routes/web.php
 * only when config('app.debug') is true.
 */
class SimulatorController extends Controller
{
    public function index(Request $request, DayRecordBuilder $dayRecords, AlertBuilder $alerts)
    {
        $schools = School::with(['gates', 'students'])->get();
        $school = $request->query('school')
            ? $schools->firstWhere('id', (int) $request->query('school'))
            : $schools->first();

        abort_if(! $school, 404, 'Seed a school first: php artisan migrate:fresh --seed');

        $gate = $request->query('gate')
            ? $school->gates->firstWhere('id', (int) $request->query('gate'))
            : $school->gates->first();

        $student = $request->query('student')
            ? $school->students->firstWhere('id', (int) $request->query('student'))
            : $school->students->first();

        $dayRows = [];
        $alertRows = [];

        if ($student) {
            $student->loadMissing('school');
            $tz = $student->school->timezone;
            $from = Carbon::now($tz)->subDays(20)->startOfDay();
            $to = Carbon::now($tz)->startOfDay();

            $dayRows = $dayRecords->forRange($student, $from, $to)->reverse()->values()->all();
            $alertRows = $alerts->forStudent($student, days: 30);
        }

        return view('simulator', [
            'schools' => $schools,
            'school' => $school,
            'gate' => $gate,
            'student' => $student,
            'scenarios' => array_map(fn (SimulationProfile $p) => $p->value, SimulationProfile::cases()),
            'dayRows' => $dayRows,
            'alertRows' => $alertRows,
            'recentTaps' => TapEvent::with(['student:id,full_name', 'gate:id,name'])
                ->whereIn('student_id', $school->students->pluck('id'))
                ->latest('tapped_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function tap(Request $request, TurnstileSimulator $simulator): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'gate_id' => 'required|integer|exists:gates,id',
            'direction' => 'nullable|in:in,out',
            'timing' => 'nullable|in:now,on-time,late',
        ]);

        $student = Student::with('school')->findOrFail($data['student_id']);
        $gate = Gate::findOrFail($data['gate_id']);

        $tap = $simulator->liveTap(
            $student,
            $gate,
            $data['direction'] ?? null,
            $data['timing'] ?? 'now',
        );

        return response()->json([
            'message' => sprintf(
                '%s tapped %s at %s%s',
                $student->full_name,
                strtoupper($tap->direction),
                $tap->tapped_at->setTimezone($student->school->timezone)->format('g:i A'),
                $tap->is_late ? ' (late)' : '',
            ),
        ]);
    }

    public function backfill(Request $request, TurnstileSimulator $simulator): JsonResponse
    {
        $data = $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'gate_id' => 'required|integer|exists:gates,id',
            'student_id' => 'nullable|integer|exists:students,id',
            'scenario' => 'required|string',
            'days' => 'required|integer|min:1|max:120',
            'fresh' => 'boolean',
        ]);

        $profile = SimulationProfile::tryFrom($data['scenario']);
        abort_if(! $profile, 422, 'Unknown scenario.');

        $school = School::findOrFail($data['school_id']);
        $gate = Gate::findOrFail($data['gate_id']);
        $students = $school->students()
            ->when($data['student_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($request->boolean('fresh')) {
            TapEvent::whereIn('student_id', $students->pluck('id'))->delete();
        }

        $summary = $simulator->backfill($students, $gate, (int) $data['days'], $profile);

        return response()->json([
            'message' => "Built {$summary->sum('taps')} taps across {$students->count()} student(s) — scenario \"{$profile->value}\".",
            'summary' => $summary,
        ]);
    }

    public function flagAbsent(): JsonResponse
    {
        Artisan::call('attendance:flag-absent');

        return response()->json(['message' => trim(Artisan::output())]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        $studentIds = Student::where('school_id', $data['school_id'])
            ->when($data['student_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->pluck('id');

        $deleted = TapEvent::whereIn('student_id', $studentIds)->delete();

        return response()->json(['message' => "Deleted {$deleted} tap event(s)."]);
    }
}
