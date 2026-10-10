<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\AppTimezone;
use PHPUnit\Framework\TestCase;

final class AppTimezoneTest extends TestCase
{
    public function testItDefaultsToBuenosAiresWhenUnset(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', AppTimezone::resolve(null)->getName());
        $this->assertSame('America/Argentina/Buenos_Aires', AppTimezone::resolve('')->getName());
    }

    public function testItUsesAValidConfiguredTimezone(): void
    {
        $this->assertSame('Europe/Madrid', AppTimezone::resolve(' Europe/Madrid ')->getName());
    }

    public function testItFallsBackToTheDefaultForAnInvalidTimezone(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', AppTimezone::resolve('Mars/Olympus_Mons')->getName());
    }

    public function testItComputesTheNumericMysqlOffset(): void
    {
        $at = new \DateTimeImmutable('2026-10-04T11:30:00+00:00');

        $this->assertSame('-03:00', AppTimezone::mysqlOffset(new \DateTimeZone('America/Argentina/Buenos_Aires'), $at));
        $this->assertSame('+00:00', AppTimezone::mysqlOffset(new \DateTimeZone('UTC'), $at));
    }
}
