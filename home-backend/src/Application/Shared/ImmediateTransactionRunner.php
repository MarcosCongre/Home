<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Runs the operation without a transaction, for storage that has none (in memory).
 */
final class ImmediateTransactionRunner implements TransactionRunner
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
