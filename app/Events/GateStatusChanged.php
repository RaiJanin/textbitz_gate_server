<?php

namespace App\Events;

use App\Models\Gate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GateStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Gate $gate) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("gate.{$this->gate->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GateStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'gate_id' => $this->gate->id,
            'school_id' => $this->gate->school_id,
            'name' => $this->gate->name,
            'status' => $this->gate->status,
            'last_seen_at' => $this->gate->last_seen_at?->toIso8601String(),
        ];
    }
}
