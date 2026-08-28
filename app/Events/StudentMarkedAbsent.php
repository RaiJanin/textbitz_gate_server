<?php

namespace App\Events;

use App\Models\Student;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class StudentMarkedAbsent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Student $student, public Carbon $date) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("student.{$this->student->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StudentMarkedAbsent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->full_name,
            'date' => $this->date->toDateString(),
        ];
    }
}
