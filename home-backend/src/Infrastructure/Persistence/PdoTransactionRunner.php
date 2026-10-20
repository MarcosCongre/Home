<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Shared\TransactionRunner;
use PDO;

final class PdoTransactionRunner implements TransactionRunner
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function run(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
