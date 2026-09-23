<?php

declare(strict_types=1);

namespace App\Application\Members\UpdateMember;

readonly class UpdateMemberCommand
{
    public function __construct(
        public int $memberId,
        public string $name,
        public string $avatar,
        public string $color
    ) {
    }
}
