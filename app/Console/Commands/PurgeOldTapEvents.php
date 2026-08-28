<?php

namespace App\Console\Commands;

use App\Models\TapEvent;
use Illuminate\Console\Command;

class PurgeOldTapEvents extends Command
{
    protected $signature = 'attendance:purge-taps';

    protected $description = 'Delete tap_events older than the configured retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('attendance.retention_days'));

        $deleted = TapEvent::where('tapped_at', '<', $cutoff)->delete();

        $this->info("Purged {$deleted} tap event(s) older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
