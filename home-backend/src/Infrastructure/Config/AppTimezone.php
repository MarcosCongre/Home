<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final class AppTimezone
{
    public const DEFAULT = 'America/Argentina/Buenos_Aires';

    /**
     * Resolves the configured timezone. An unknown identifier falls back to the
     * default (and is logged) so a typo in APP_TIMEZONE does not take the API down.
     */
    public static function resolve(?string $configured): \DateTimeZone
    {
        $name = trim((string) $configured);

        if ($name === '') {
            return new \DateTimeZone(self::DEFAULT);
        }

        if (!in_array($name, \DateTimeZone::listIdentifiers(), true)) {
            error_log(sprintf('Invalid APP_TIMEZONE "%s", falling back to %s', $name, self::DEFAULT));

            return new \DateTimeZone(self::DEFAULT);
        }

        return new \DateTimeZone($name);
    }

    /**
     * Numeric offset (e.g. "-03:00") for MySQL `SET time_zone`, which only
     * accepts named zones when the server has its timezone tables loaded.
     */
    public static function mysqlOffset(\DateTimeZone $timezone, ?\DateTimeImmutable $at = null): string
    {
        return ($at ?? new \DateTimeImmutable('now'))->setTimezone($timezone)->format('P');
    }
}
