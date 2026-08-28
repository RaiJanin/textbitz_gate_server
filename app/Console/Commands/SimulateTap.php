<?php

namespace App\Console\Commands;

use App\Models\Gate;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\TurnstileSimulator;
use Illuminate\Console\Command;

class SimulateTap extends Command
{
    protected $signature = 'gate:tap
        {rfid? : rfid_uid to tap (default: first student in the school)}
        {--school= : School id (default: first school)}
        {--gate= : Gate id (default: first gate in the school)}
        {--in : Force an arrival}
        {--out : Force a departure}
        {--on-time : Timestamp just before the cutoff}
        {--late : Timestamp just after the cutoff}';

    protected $description = 'Fire a single live turnstile tap through the full pipeline (broadcast + push)';

    public function handle(TurnstileSimulator $simulator): int
    {
        $school = $this->option('school') ? School::findOrFail($this->option('school')) : School::firstOrFail();
        $gate = $this->option('gate') ? $school->gates()->findOrFail($this->option('gate')) : $school->gates()->firstOrFail();

        $student = $this->argument('rfid')
            ? $school->students()->where('rfid_uid', $this->argument('rfid'))->firstOrFail()
            : $school->students()->firstOrFail();

        $direction = match (true) {
            (bool) $this->option('out') => 'out',
            (bool) $this->option('in') => 'in',
            default => null,
        };

        $timing = match (true) {
            (bool) $this->option('late') => 'late',
            (bool) $this->option('on-time') => 'on-time',
            default => 'now',
        };

        $tap = $simulator->liveTap($student, $gate, $direction, $timing);

        $this->info(sprintf(
            '%s tapped %s at %s — %s%s',
            $student->full_name,
            strtoupper($tap->direction),
            $gate->name,
            $tap->tapped_at->setTimezone($school->timezone)->format('D M j, g:i A'),
            $tap->is_late ? '  [LATE]' : '',
        ));

        $this->line('Broadcast on private-student.'.$student->id.' and queued for push fan-out.');

        return self::SUCCESS;
    }
}
