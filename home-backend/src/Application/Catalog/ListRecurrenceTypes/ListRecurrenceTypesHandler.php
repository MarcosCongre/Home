<?php

declare(strict_types=1);

namespace App\Application\Catalog\ListRecurrenceTypes;

use App\Application\Shared\ImmediateTransactionRunner;
use App\Application\Shared\TransactionRunner;
use App\Domain\Tasks\Catalog\DefaultCatalogs;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use DateTimeImmutable;

final class ListRecurrenceTypesHandler
{
    private readonly TransactionRunner $transactionRunner;

    public function __construct(
        private readonly RecurrenceTypeRepositoryInterface $recurrenceTypeRepository,
        ?TransactionRunner $transactionRunner = null
    ) {
        $this->transactionRunner = $transactionRunner ?? new ImmediateTransactionRunner();
    }

    /**
     * @return array<int, RecurrenceType>
     */
    public function handle(ListRecurrenceTypesQuery $query): array
    {
        if (trim($query->householdId) === '') {
            throw new \InvalidArgumentException('householdId is required.');
        }

        // Seed only a household that never had a recurrence type: deleting them all must not bring the defaults back.
        if ($this->recurrenceTypeRepository->countAllByHouseholdId($query->householdId) === 0) {
            $this->transactionRunner->run(function () use ($query): void {
                $now = new DateTimeImmutable();
                foreach (DefaultCatalogs::RECURRENCE_TYPES as $name => $intervalDays) {
                    $this->recurrenceTypeRepository->save(RecurrenceType::create(0, $query->householdId, $name, $intervalDays, $now));
                }
            });
        }

        return $this->recurrenceTypeRepository->findByHouseholdId($query->householdId);
    }
}
