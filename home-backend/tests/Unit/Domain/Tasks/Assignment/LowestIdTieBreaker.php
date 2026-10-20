<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tasks\Assignment;

use App\Domain\Tasks\Assignment\TieBreaker;

/**
 * Deterministic tie-breaker for tests: always picks the lowest member id.
 */
final class LowestIdTieBreaker implements TieBreaker
{
    public function pick(array $candidateIds): int
    {
        return min($candidateIds);
    }
}
