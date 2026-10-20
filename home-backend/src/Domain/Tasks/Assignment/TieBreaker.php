<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Assignment;

interface TieBreaker
{
    /**
     * Picks one member among candidates that share the lowest load.
     *
     * @param non-empty-list<int> $candidateIds
     */
    public function pick(array $candidateIds): int;
}
