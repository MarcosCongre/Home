<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Catalog\CreateRecurrenceType\CreateRecurrenceTypeCommand;
use App\Application\Catalog\CreateRecurrenceType\CreateRecurrenceTypeHandler;
use App\Application\Catalog\DeleteRecurrenceType\DeleteRecurrenceTypeCommand;
use App\Application\Catalog\DeleteRecurrenceType\DeleteRecurrenceTypeHandler;
use App\Application\Catalog\ListRecurrenceTypes\ListRecurrenceTypesHandler;
use App\Application\Catalog\ListRecurrenceTypes\ListRecurrenceTypesQuery;
use App\Application\Catalog\UpdateRecurrenceType\UpdateRecurrenceTypeCommand;
use App\Application\Catalog\UpdateRecurrenceType\UpdateRecurrenceTypeHandler;
use App\Application\Shared\TransactionRunner;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;

final class RecurrenceTypeController
{
    public function __construct(
        private RecurrenceTypeRepositoryInterface $recurrenceTypeRepository,
        private ?TransactionRunner $transactionRunner = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $handler = new CreateRecurrenceTypeHandler($this->recurrenceTypeRepository);
        $recurrenceType = $handler->handle(new CreateRecurrenceTypeCommand(
            householdId: (string) ($payload['householdId'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            intervalDays: $this->intervalDays($payload)
        ));

        return $this->serializeRecurrenceType($recurrenceType);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $recurrenceTypeId, string $householdId, array $payload): array
    {
        $handler = new UpdateRecurrenceTypeHandler($this->recurrenceTypeRepository);
        $recurrenceType = $handler->handle(new UpdateRecurrenceTypeCommand(
            recurrenceTypeId: $recurrenceTypeId,
            householdId: $householdId,
            name: (string) ($payload['name'] ?? ''),
            intervalDays: $this->intervalDays($payload)
        ));

        return $this->serializeRecurrenceType($recurrenceType);
    }

    public function delete(int $recurrenceTypeId, string $householdId): void
    {
        (new DeleteRecurrenceTypeHandler($this->recurrenceTypeRepository))->handle(new DeleteRecurrenceTypeCommand($recurrenceTypeId, $householdId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $householdId): array
    {
        $handler = new ListRecurrenceTypesHandler($this->recurrenceTypeRepository, $this->transactionRunner);
        $recurrenceTypes = $handler->handle(new ListRecurrenceTypesQuery($householdId));

        return array_map(fn (RecurrenceType $type): array => $this->serializeRecurrenceType($type), $recurrenceTypes);
    }

    /**
     * JSON integers or integer strings only; fractions and other text are rejected instead of truncated.
     *
     * @param array<string, mixed> $payload
     */
    private function intervalDays(array $payload): int
    {
        $value = $payload['intervalDays'] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?[0-9]+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        throw new \InvalidArgumentException('intervalDays must be an integer.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecurrenceType(RecurrenceType $recurrenceType): array
    {
        return [
            'id' => $recurrenceType->id(),
            'householdId' => $recurrenceType->householdId(),
            'name' => $recurrenceType->name(),
            'intervalDays' => $recurrenceType->intervalDays(),
        ];
    }
}
