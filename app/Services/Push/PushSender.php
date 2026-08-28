<?php

namespace App\Services\Push;

interface PushSender
{
    /**
     * Deliver a notification to the given device tokens.
     *
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     * @return list<string>  Tokens the provider rejected as unregistered — the caller should delete these.
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array;
}
