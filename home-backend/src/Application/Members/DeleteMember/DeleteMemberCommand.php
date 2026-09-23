<?php

declare(strict_types=1);

namespace App\Application\Members\DeleteMember;

readonly class DeleteMemberCommand
{
    public function __construct(
        public int $memberId
    ) {
    }
}
