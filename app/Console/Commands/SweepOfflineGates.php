<?php

namespace App\Console\Commands;

use App\Events\GateStatusChanged;
use App\Models\Gate;
use Illuminate\Console\Command;

class SweepOfflineGates extends Command
{
    protected $signature = 'attendance:sweep-gates';

    protected $description = 'Flip gates to offline when they have not been seen recently';

    public function handle(): int
    {
        $threshold = now()->subMinutes((int) config('attendance.gate_offline_after_minutes'));

        $stale = Gate::where('status', Gate::STATUS_ONLINE)
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold))
            ->get();

        foreach ($stale as $gate) {
            $gate->forceFill(['status' => Gate::STATUS_OFFLINE])->save();
            GateStatusChanged::dispatch($gate);
        }

        $this->info("Marked {$stale->count()} gate(s) offline.");

        return self::SUCCESS;
    }
}
