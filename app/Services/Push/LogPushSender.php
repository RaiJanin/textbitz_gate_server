<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

/**
 * Default push sender used until real FCM credentials are configured
 * (FCM_ENABLED=false). Logs the payload instead of delivering it so the
 * fan-out pipeline can be exercised end to end without Firebase.
 */
class LogPushSender implements PushSender
{
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        Log::info('[push:log] would deliver notification', [
            'tokens' => $tokens,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return [];
    }
}
