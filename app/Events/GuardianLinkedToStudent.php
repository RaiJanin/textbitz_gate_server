<?php

namespace App\Events;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuardianLinkedToStudent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Guardian $guardian, public Student $student) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("student.{$this->student->id}"),
        ];

        if ($this->guardian->user_id) {
            $channels[] = new PrivateChannel("user.{$this->guardian->user_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'GuardianLinkedToStudent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'guardian_id' => $this->guardian->id,
            'student_id' => $this->student->id,
            'student_name' => $this->student->full_name,
        ];
    }
}
