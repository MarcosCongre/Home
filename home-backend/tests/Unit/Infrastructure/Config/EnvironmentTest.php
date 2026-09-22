<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testItLoadsValuesFromDotEnvFile(): void
    {
        $envFile = sys_get_temp_dir() . '/home-backend-test.env';
        file_put_contents($envFile, "APP_ENV=production\nAPP_DEBUG=false\nJWT_SECRET=test-secret\nDB_DSN=sqlite:/tmp/test.sqlite\n");

        $environment = Environment::fromFile($envFile);

        $this->assertSame('production', $environment->getString('APP_ENV'));
        $this->assertFalse($environment->getBool('APP_DEBUG'));
        $this->assertSame('test-secret', $environment->getString('JWT_SECRET'));
        $this->assertSame('sqlite:/tmp/test.sqlite', $environment->getString('DB_DSN'));

        unlink($envFile);
    }

    public function testItRejectsMissingRequiredValues(): void
    {
        $envFile = sys_get_temp_dir() . '/home-backend-missing.env';
        file_put_contents($envFile, "APP_ENV=production\n");

        $environment = Environment::fromFile($envFile);

        $this->expectException(\RuntimeException::class);
        $environment->required('JWT_SECRET');

        unlink($envFile);
    }
}
