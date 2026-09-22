<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\JwtTokenService;
use PHPUnit\Framework\TestCase;

final class JwtTokenServiceTest extends TestCase
{
    public function testItEncodesAndDecodesPayloads(): void
    {
        $service = new JwtTokenService('test-secret');
        $token = $service->encode(['sub' => 'user-123', 'role' => 'member']);

        $this->assertNotSame('', $token);
        $this->assertSame('user-123', $service->decode($token)['sub']);
        $this->assertSame('member', $service->decode($token)['role']);
    }

    public function testItRejectsTamperedTokens(): void
    {
        $service = new JwtTokenService('test-secret');
        $token = $service->encode(['sub' => 'user-123']);

        $tampered = str_replace('a', 'b', $token, $count);
        if ($count === 0) {
            $tampered = $token . 'x';
        }

        $this->assertSame([], $service->decode($tampered));
    }
}
