<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Assignment;

final class RandomTieBreaker implements TieBreaker
{
    public function pick(array $candidateIds): int
    {
        $candidateIds = array_values($candidateIds);

        return $candidateIds[random_int(0, count($candidateIds) - 1)];
    }
}
