<?php

declare(strict_types=1);

namespace App\Application\Alerts\CreateAlert;

use DateTimeImmutable;

readonly class CreateAlertCommand
{
    public function __construct(
        public string $householdId,
        public ?int $memberId,
        public string $title,
        public string $body,
        public string $icon,
        public bool $urgent,
        public ?DateTimeImmutable $createdAt = null
    ) {
    }
}
