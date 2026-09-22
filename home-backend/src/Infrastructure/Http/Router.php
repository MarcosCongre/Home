<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Tasks\TaskRepositoryInterface;

final class Router
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ?\App\Domain\Members\MemberRepositoryInterface $memberRepository = null
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function dispatch(array $request): array
    {
        $method = strtoupper((string) ($request['REQUEST_METHOD'] ?? 'GET'));
        $path = (string) ($request['PATH_INFO'] ?? '/');

        if ($method === 'POST' && $path === '/tasks') {
            $controller = new TaskController($this->taskRepository);
            return $controller->create($this->decodeJson((string) ($request['php://input'] ?? '{}')));
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)/complete$#', $path, $matches) === 1) {
            $controller = new TaskController($this->taskRepository);
            $payload = $this->decodeJson((string) ($request['php://input'] ?? '{}'));
            return $controller->complete($matches[1], (string) ($payload['userId'] ?? ''));
        }

        if ($method === 'GET' && $path === '/tasks') {
            $controller = new TaskController($this->taskRepository);
            $householdId = (string) ($request['householdId'] ?? '');
            if ($householdId === '' && isset($request['QUERY_STRING'])) {
                parse_str((string) $request['QUERY_STRING'], $queryParams);
                $householdId = (string) ($queryParams['householdId'] ?? '');
            }

            return $controller->list($householdId);
        }

        if ($this->memberRepository !== null && $method === 'POST' && $path === '/members') {
            $controller = new MemberController($this->memberRepository);
            return $controller->create($this->decodeJson((string) ($request['php://input'] ?? '{}')));
        }

        if ($this->memberRepository !== null && $method === 'PATCH' && preg_match('#^/members/([^/]+)$#', $path, $matches) === 1) {
            $controller = new MemberController($this->memberRepository);
            $payload = $this->decodeJson((string) ($request['php://input'] ?? '{}'));
            return $controller->update($matches[1], $payload);
        }

        if ($this->memberRepository !== null && $method === 'DELETE' && preg_match('#^/members/([^/]+)$#', $path, $matches) === 1) {
            $controller = new MemberController($this->memberRepository);
            $controller->delete($matches[1]);
            return ['deleted' => true, 'id' => $matches[1]];
        }

        if ($this->memberRepository !== null && $method === 'GET' && $path === '/members') {
            $controller = new MemberController($this->memberRepository);
            $householdId = (string) ($request['householdId'] ?? '');
            if ($householdId === '' && isset($request['QUERY_STRING'])) {
                parse_str((string) $request['QUERY_STRING'], $queryParams);
                $householdId = (string) ($queryParams['householdId'] ?? '');
            }

            return $controller->list($householdId);
        }

        return ['error' => 'Not found'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
