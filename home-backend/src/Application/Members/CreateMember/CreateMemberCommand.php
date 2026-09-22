<?php

declare(strict_types=1);

namespace App\Application\Members\CreateMember;

readonly class CreateMemberCommand
{
    public function __construct(
        public string $name,
        public string $avatar,
        public string $color,
        public string $householdId
    ) {
    }
}
