<?php

declare(strict_types=1);

namespace App\Infrastructure\Bootstrap;

use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use App\Domain\Tasks\TaskRepositoryInterface;
use App\Infrastructure\Http\Router;

final class App
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ?MemberRepositoryInterface $memberRepository = null,
        private ?AlertRepositoryInterface $alertRepository = null,
        private ?CategoryRepositoryInterface $categoryRepository = null,
        private ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository = null
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function handle(array $request): array
    {
        return (new Router(
            $this->taskRepository,
            $this->memberRepository,
            $this->alertRepository,
            categoryRepository: $this->categoryRepository,
            recurrenceTypeRepository: $this->recurrenceTypeRepository
        ))->dispatch($request);
    }
}
