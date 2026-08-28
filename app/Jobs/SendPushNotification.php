<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\Push\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(PushSender $sender): void
    {
        $tokens = DeviceToken::where('user_id', $this->userId)->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        $unregistered = $sender->send($tokens, $this->title, $this->body, $this->data);

        if ($unregistered !== []) {
            DeviceToken::where('user_id', $this->userId)
                ->whereIn('token', $unregistered)
                ->delete();
        }
    }
}
