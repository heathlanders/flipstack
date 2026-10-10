<?php

namespace App\Services\Notification\Fcm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends single push messages to Firebase Cloud Messaging HTTP v1.
 *
 * Auth is via a Google service account JSON whose path is configured in
 * services.fcm.credentials_path. The OAuth2 access token is cached for 50
 * minutes to avoid re-signing on every send.
 *
 * Returns true on success, false on failure (failures are logged but never
 * thrown — push is best-effort).
 */
class FcmDispatcher
{
    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    private const TOKEN_TTL_SECONDS = 3000;

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return ! empty($this->projectId()) && $this->loadCredentials() !== null;
    }

    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return false;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
            ],
        ];

        try {
            $resp = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", $payload);

            if ($resp->successful()) {
                return true;
            }

            Log::warning('fcm.send_failed', [
                'status' => $resp->status(),
                'error' => $resp->json('error.message'),
                'token_suffix' => substr($token, -8),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('fcm.send_exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function projectId(): ?string
    {
        return config('services.fcm.project_id');
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            return $this->fetchAccessToken();
        });
    }

    private function fetchAccessToken(): ?string
    {
        $creds = $this->loadCredentials();
        if (! $creds) {
            return null;
        }

        $jwt = $this->signJwt($creds);
        if (! $jwt) {
            return null;
        }

        try {
            $resp = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $resp->successful()) {
                Log::warning('fcm.token_exchange_failed', ['status' => $resp->status(), 'body' => $resp->body()]);

                return null;
            }

            return $resp->json('access_token');
        } catch (\Throwable $e) {
            Log::warning('fcm.token_exchange_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function loadCredentials(): ?array
    {
        $path = config('services.fcm.credentials_path');
        if (! $path || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return $json;
    }

    private function signJwt(array $creds): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim = [
            'iss' => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($claim)),
        ];
        $input = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($input, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
        if (! $ok) {
            Log::warning('fcm.jwt_sign_failed');

            return null;
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
