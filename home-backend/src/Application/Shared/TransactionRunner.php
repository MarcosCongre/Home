<?php

declare(strict_types=1);

namespace App\Application\Shared;

interface TransactionRunner
{
    /**
     * Runs the operation atomically: every write inside it is committed together
     * or, when it throws, none is. The exception is rethrown.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}
