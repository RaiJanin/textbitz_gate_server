<?php

namespace App\Events;

use App\Models\TapEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TapRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TapEvent $tap)
    {
        $this->tap->loadMissing(['student', 'gate']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("student.{$this->tap->student_id}"),
            new PrivateChannel("gate.{$this->tap->gate_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TapRecorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->tap->id,
            'student_id' => $this->tap->student_id,
            'student_name' => $this->tap->student?->full_name,
            'gate_id' => $this->tap->gate_id,
            'gate_name' => $this->tap->gate?->name,
            'direction' => $this->tap->direction,
            'tapped_at' => $this->tap->tapped_at?->toIso8601String(),
            'is_late' => (bool) $this->tap->is_late,
        ];
    }
}
