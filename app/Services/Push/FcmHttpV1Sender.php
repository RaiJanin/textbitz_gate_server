<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 sender.
 *
 * Only used when config('services.fcm.enabled') is true and a service-account
 * JSON key is configured at config('services.fcm.credentials'). Kept dependency
 * free: the OAuth2 bearer token is minted from the service account with a
 * self-signed RS256 JWT rather than pulling in google/auth.
 */
class FcmHttpV1Sender implements PushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens === []) {
            return [];
        }

        $projectId = config('services.fcm.project_id');
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

    private function accessToken(): string
    {
        return Cache::remember('fcm:access_token', now()->addMinutes(50), function () {
            $credentialsPath = config('services.fcm.credentials');

            if (! $credentialsPath || ! is_file($credentialsPath)) {
                throw new RuntimeException('FCM credentials file not found at: '.$credentialsPath);
            }

            /** @var array{client_email: string, private_key: string, token_uri?: string} $sa */
            $sa = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);

            $now = time();
            $claims = [
                'iss' => $sa['client_email'],
                'scope' => self::SCOPE,
                'aud' => $sa['token_uri'] ?? self::TOKEN_URI,
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
                $this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR)),
            ];

            $signature = '';
            openssl_sign(implode('.', $segments), $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->post($sa['token_uri'] ?? self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw();

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
