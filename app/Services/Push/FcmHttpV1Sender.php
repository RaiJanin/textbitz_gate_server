<?php

namespace App\Services\Push;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 sender.
 *
 * Only used when config('services.fcm.enabled') is true and a service-account
 * JSON key is configured at config('services.fcm.credentials'). The OAuth2
 * bearer token is minted with google/auth's ServiceAccountCredentials — the
 * same mechanism the client's fatlum/nativephp-push FcmSender uses — so the
 * token exchange, clock-skew handling and retries are Google's, not ours.
 */
class FcmHttpV1Sender implements PushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens === []) {
            return [];
        }

        $projectId = config('services.fcm.project_id');

        if (empty($projectId)) {
            throw new RuntimeException('services.fcm.project_id (FCM_PROJECT_ID) is not configured.');
        }

        $accessToken = $this->accessToken();
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $stringData = array_map(static fn ($value) => (string) $value, $data);
        $unregistered = [];

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $stringData,
                        'android' => ['priority' => 'high'],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                    ],
                ]);

            if ($response->successful()) {
                continue;
            }

            $status = $response->json('error.status');

            if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $unregistered[] = $token;
                continue;
            }

            Log::warning('[push:fcm] delivery failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        return $unregistered;
    }

    /**
     * FCM v1 access token, cached just short of its hour-long lifetime so a busy
     * queue worker isn't re-signing a JWT on every job.
     */
    private function accessToken(): string
    {
        return Cache::remember('fcm:access_token', now()->addMinutes(50), function () {
            $configured = (string) config('services.fcm.credentials');

            // Accept an absolute path (Docker: /app/credentials/...) or one
            // relative to the project root (local dev).
            $credentialsPath = is_file($configured)
                ? $configured
                : base_path($configured);

            if ($configured === '' || ! is_file($credentialsPath)) {
                throw new RuntimeException('FCM credentials file not found at: '.($configured ?: '(unset)'));
            }

            $token = (new ServiceAccountCredentials(self::SCOPE, $credentialsPath))->fetchAuthToken();

            return $token['access_token']
                ?? throw new RuntimeException('google/auth returned no access_token for the FCM service account.');
        });
    }
}
