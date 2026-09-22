<?php

declare(strict_types=1);

namespace App\Application\Members\ListMembers;

readonly class ListMembersQuery
{
    public function __construct(
        public string $householdId
    ) {
    }
}
