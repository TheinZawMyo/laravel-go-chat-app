<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class GatewayToken
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 300,
    ) {
        if ($this->secret === '') {
            throw new \InvalidArgumentException('The gateway token secret must not be empty.');
        }

        if ($this->ttlSeconds < 1) {
            throw new \InvalidArgumentException('The gateway token TTL must be positive.');
        }
    }

    /**
     * @return array{token: string, expires_at: string}
     */
    public function issue(User $user): array
    {
        $issuedAt = Carbon::now()->getTimestamp();
        $expiresAt = $issuedAt + $this->ttlSeconds;

        $payload = [
            'sub' => $user->id,
            'aud' => 'gateway',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
        ];

        $encodedPayload = self::base64UrlEncode(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $signature = hash_hmac('sha256', $encodedPayload, $this->secret, true);

        return [
            'token' => $encodedPayload.'.'.self::base64UrlEncode($signature),
            'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
        ];
    }

    /**
     * @return array{sub: string, aud: string, iat: int, exp: int, jti: string}|null
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $signature = self::base64UrlDecode($encodedSignature);

        if ($signature === null) {
            return null;
        }

        $expected = hash_hmac('sha256', $encodedPayload, $this->secret, true);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = self::base64UrlDecode($encodedPayload);

        if ($payload === null) {
            return null;
        }

        try {
            $claims = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($claims)) {
            return null;
        }

        if (($claims['aud'] ?? null) !== 'gateway' || ! is_string($claims['sub'] ?? null)) {
            return null;
        }

        if (! is_int($claims['iat'] ?? null) || ! is_int($claims['exp'] ?? null)) {
            return null;
        }

        $now = Carbon::now()->getTimestamp();

        if ($claims['exp'] <= $now || $claims['iat'] > $now + 60) {
            return null;
        }

        return [
            'sub' => $claims['sub'],
            'aud' => $claims['aud'],
            'iat' => $claims['iat'],
            'exp' => $claims['exp'],
            'jti' => is_string($claims['jti'] ?? null) ? $claims['jti'] : '',
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
