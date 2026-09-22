<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class JwtTokenService
{
    public function __construct(
        private string $secretKey = 'development-secret-key'
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = $this->base64UrlEncode($payload);
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $body, $this->secretKey, true));

        return $header . '.' . $body . '.' . $signature;
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return [];
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $this->secretKey, true));

        if (!hash_equals($expected, $signature)) {
            return [];
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|string $value
     */
    private function base64UrlEncode(array|string $value): string
    {
        $encoded = is_array($value) ? json_encode($value) : $value;

        if ($encoded === false || $encoded === null) {
            return '';
        }

        return rtrim(strtr(base64_encode($encoded), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
