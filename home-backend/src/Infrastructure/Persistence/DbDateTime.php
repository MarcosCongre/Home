<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class DbDateTime
{
    private const FORMAT = 'Y-m-d H:i:s';

    /**
     * Formats a datetime for TIMESTAMP/DATETIME columns as a naive value in the
     * app timezone. MySQL/MariaDB do not understand ISO 8601 offsets (they
     * truncate or reject them), and the session time_zone matches the app
     * timezone, so the value is converted before the offset is dropped.
     */
    public static function format(\DateTimeInterface $value): string
    {
        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format(self::FORMAT);
    }
}
