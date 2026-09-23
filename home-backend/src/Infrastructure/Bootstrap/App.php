<?php

declare(strict_types=1);

namespace App\Infrastructure\Bootstrap;

use App\Domain\Tasks\TaskRepositoryInterface;
use App\Infrastructure\Http\Router;

final class App
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function handle(array $request): array
    {
        return (new Router($this->taskRepository))->dispatch($request);
    }
}
