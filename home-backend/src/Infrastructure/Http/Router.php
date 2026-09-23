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
        if ($path === '/' && isset($request['REQUEST_URI'])) {
            $path = (string) parse_url((string) $request['REQUEST_URI'], PHP_URL_PATH);
        }

        if ($method === 'POST' && $path === '/tasks') {
            $controller = new TaskController($this->taskRepository);
            return $controller->create($this->decodeJson($this->body($request)));
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)$#', $path, $matches) === 1) {
            $controller = new TaskController($this->taskRepository);
            return $controller->update($this->positiveId($matches[1]), $this->decodeJson($this->body($request)));
        }

        if ($method === 'DELETE' && preg_match('#^/tasks/([^/]+)$#', $path, $matches) === 1) {
            $id = $this->positiveId($matches[1]);
            (new TaskController($this->taskRepository))->delete($id);
            return ['deleted' => true, 'id' => $id];
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)/complete$#', $path, $matches) === 1) {
            $controller = new TaskController($this->taskRepository);
            $payload = $this->decodeJson($this->body($request));
            return $controller->complete($this->positiveId($matches[1]), (string) ($payload['userId'] ?? ''));
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
            return $controller->create($this->decodeJson($this->body($request)));
        }

        if ($this->memberRepository !== null && $method === 'PATCH' && preg_match('#^/members/([^/]+)$#', $path, $matches) === 1) {
            $controller = new MemberController($this->memberRepository);
            $payload = $this->decodeJson($this->body($request));
            return $controller->update($this->positiveId($matches[1]), $payload);
        }

        if ($this->memberRepository !== null && $method === 'DELETE' && preg_match('#^/members/([^/]+)$#', $path, $matches) === 1) {
            $controller = new MemberController($this->memberRepository);
            $id = $this->positiveId($matches[1]);
            $controller->delete($id);
            return ['deleted' => true, 'id' => $id];
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

    /** @param array<string, mixed> $request */
    private function body(array $request): string
    {
        if (isset($request['php://input'])) {
            return (string) $request['php://input'];
        }

        if (isset($request['rawBody'])) {
            return (string) $request['rawBody'];
        }

        return file_get_contents('php://input') ?: '{}';
    }

    private function positiveId(string $value): int
    {
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            throw new \InvalidArgumentException('Entity ids must be positive integers.');
        }

        return (int) $value;
    }
}
