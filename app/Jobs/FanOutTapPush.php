<?php

namespace App\Jobs;

use App\Models\NotificationPreference;
use App\Models\TapEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After a tap is recorded, enqueue one push per linked recipient (guardians +
 * the student's own account), skipping anyone whose notification_preferences
 * have that category turned off.
 */
class FanOutTapPush implements ShouldQueue
{
    use Queueable;

    public function __construct(public TapEvent $tap) {}

    public function handle(): void
    {
        $this->tap->loadMissing([
            'student.guardians.user',
            'student.studentAccount.user',
            'gate',
        ]);

        $student = $this->tap->student;

        if (! $student) {
            return;
        }

        $kind = $this->kind();
        [$title, $body] = $this->message();

        $data = [
            'type' => $kind,
            'tap_id' => $this->tap->id,
            'student_id' => $student->id,
            'direction' => $this->tap->direction,
            'gate_name' => $this->tap->gate?->name,
            'tapped_at' => $this->tap->tapped_at?->toIso8601String(),
            'is_late' => $this->tap->is_late ? '1' : '0',
        ];

        /** @var array<int, array{0: User, 1: string}> $recipients */
        $recipients = [];

        foreach ($student->guardians as $guardian) {
            if ($guardian->user) {
                $recipients[] = [$guardian->user, NotificationPreference::ROLE_GUARDIAN];
            }
        }

        if ($student->studentAccount?->user) {
            $recipients[] = [$student->studentAccount->user, NotificationPreference::ROLE_STUDENT];
        }

        foreach ($recipients as [$user, $role]) {
            if (! $this->shouldNotify($user, $role, $kind)) {
                continue;
            }

            SendPushNotification::dispatch($user->id, $title, $body, $data);
        }
    }

    private function kind(): string
    {
        return match (true) {
            $this->tap->direction === TapEvent::DIRECTION_IN && $this->tap->is_late => 'late_alert',
            $this->tap->direction === TapEvent::DIRECTION_IN => 'arrival',
            default => 'departure',
        };
    }

    private function shouldNotify(User $user, string $role, string $kind): bool
    {
        $prefs = $user->preferencesFor($role);

        if ($prefs->allows($kind)) {
            return true;
        }

        // A late arrival still counts as an arrival for anyone who wants arrivals.
        return $kind === 'late_alert' && $prefs->allows('arrival');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(): array
    {
        $student = $this->tap->student;
        $first = str($student->full_name)->before(' ')->toString() ?: $student->full_name;
        $gate = $this->tap->gate?->name ?? 'the gate';
        $time = $this->tap->tapped_at
            ?->setTimezone($student->school->timezone)
            ->format('g:i A');

        return match ($this->kind()) {
            'late_alert' => ["Late arrival", "{$first} tapped IN at {$gate} — {$time} (after cutoff)."],
            'arrival' => ["{$first} arrived", "{$first} tapped IN at {$gate} — {$time}."],
            default => ["{$first} left", "{$first} tapped OUT at {$gate} — {$time}."],
        };
    }
}
