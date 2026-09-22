<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final class Environment
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        private array $values = []
    ) {
    }

    /**
     * @return self
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return new self();
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
            $values[trim($name)] = trim($value);
        }

        return new self($values);
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) ($this->values[$key] ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->values[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function required(string $key): string
    {
        $value = $this->getString($key);

        if ($value === '') {
            throw new \RuntimeException(sprintf('Missing required environment variable: %s', $key));
        }

        return $value;
    }
}
