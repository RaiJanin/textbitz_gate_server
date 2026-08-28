<?php

namespace App\Services\Attendance;

use App\Events\GateStatusChanged;
use App\Events\TapRecorded;
use App\Jobs\FanOutTapPush;
use App\Models\Gate;
use App\Models\Student;
use App\Models\TapEvent;
use Illuminate\Support\Carbon;

/**
 * The single place a tap becomes a row. Used by the real ingest endpoint, the
 * turnstile simulator and the `gate:*` commands so they all exercise the same
 * direction / lateness resolution and side effects.
 */
class RecordTap
{
    public function __construct(private TapResolver $resolver) {}

    /**
     * Full "a card was tapped now" pipeline: resolve, persist, mark the gate
     * online, broadcast and fan out push notifications.
     *
     * @param  'in'|'out'|null  $forceDirection
     */
    public function record(
        Student $student,
        Gate $gate,
        ?Carbon $tappedAt = null,
        ?string $forceDirection = null,
        string $source = 'ingest',
    ): TapEvent {
        $tappedAt ??= Carbon::now();

        $this->touchGate($gate);

        $tap = $this->create($student, $gate, $tappedAt, $forceDirection, $source);

        TapRecorded::dispatch($tap);
        FanOutTapPush::dispatch($tap);

        return $tap;
    }

    /**
     * Historical tap: create the row only — no gate status change, no broadcast,
     * no push. Used when back-filling weeks of simulated history.
     *
     * @param  'in'|'out'|null  $forceDirection
     */
    public function backfill(
        Student $student,
        Gate $gate,
        Carbon $tappedAt,
        ?string $forceDirection = null,
        string $source = 'simulator',
    ): TapEvent {
        return $this->create($student, $gate, $tappedAt, $forceDirection, $source);
    }

    private function create(
        Student $student,
        Gate $gate,
        Carbon $tappedAt,
        ?string $forceDirection,
        string $source,
    ): TapEvent {
        $direction = $forceDirection ?? $this->resolver->resolveDirection($student, $tappedAt);
        $isLate = $this->resolver->isLate($student->school, $direction, $tappedAt);

        return TapEvent::create([
            'student_id' => $student->id,
            'gate_id' => $gate->id,
            'direction' => $direction,
            // Persist the instant in UTC; readers convert to the school's tz.
            'tapped_at' => $tappedAt->copy()->utc(),
            'is_late' => $isLate,
            'source' => $source,
            'synced_at' => now(),
        ]);
    }

    private function touchGate(Gate $gate): void
    {
        $wasOffline = $gate->status !== Gate::STATUS_ONLINE;

        $gate->forceFill([
            'status' => Gate::STATUS_ONLINE,
            'last_seen_at' => now(),
        ])->save();

        if ($wasOffline) {
            GateStatusChanged::dispatch($gate);
        }
    }
}
