<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Shared\TransactionRunner;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use App\Domain\Tasks\TaskRepositoryInterface;

final class Router
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ?MemberRepositoryInterface $memberRepository = null,
        private ?AlertRepositoryInterface $alertRepository = null,
        private ?TransactionRunner $transactionRunner = null,
        private ?CategoryRepositoryInterface $categoryRepository = null,
        private ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository = null
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
            $controller = new TaskController($this->taskRepository, $this->alertRepository, $this->memberRepository);
            return $controller->create($this->decodeJson($this->body($request)));
        }

        if ($this->memberRepository !== null && $method === 'POST' && $path === '/tasks/auto-assign') {
            $controller = new TaskController($this->taskRepository, $this->alertRepository, $this->memberRepository, $this->transactionRunner);
            $payload = $this->decodeJson($this->body($request));
            return $controller->autoAssign((string) ($payload['householdId'] ?? ''));
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)$#', $path, $matches) === 1) {
            $controller = new TaskController($this->taskRepository, $this->alertRepository, $this->memberRepository);
            return $controller->update($this->positiveId($matches[1]), $this->decodeJson($this->body($request)));
        }

        if ($method === 'DELETE' && preg_match('#^/tasks/([^/]+)$#', $path, $matches) === 1) {
            $id = $this->positiveId($matches[1]);
            (new TaskController($this->taskRepository, $this->alertRepository))->delete($id);
            return ['deleted' => true, 'id' => $id];
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)/complete$#', $path, $matches) === 1) {
            $controller = new TaskController($this->taskRepository, $this->alertRepository, $this->memberRepository);
            $payload = $this->decodeJson($this->body($request));
            return $controller->complete(
                $this->positiveId($matches[1]),
                (string) ($payload['userId'] ?? ''),
                $payload['nextAssigneeId'] ?? null
            );
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)/reopen$#', $path, $matches) === 1) {
            $controller = new TaskController($this->taskRepository, $this->alertRepository);
            return $controller->reopen($this->positiveId($matches[1]));
        }

        if ($method === 'GET' && $path === '/tasks') {
            $controller = new TaskController($this->taskRepository, $this->alertRepository);
            $householdId = (string) ($request['householdId'] ?? '');
            if ($householdId === '' && isset($request['QUERY_STRING'])) {
                parse_str((string) $request['QUERY_STRING'], $queryParams);
                $householdId = (string) ($queryParams['householdId'] ?? '');
            }

            return $controller->list($householdId);
        }

        if ($this->alertRepository !== null && $method === 'GET' && $path === '/alerts') {
            $controller = new AlertController($this->alertRepository, $this->memberRepository);
            $householdId = (string) ($request['householdId'] ?? '');
            if ($householdId === '' && isset($request['QUERY_STRING'])) {
                parse_str((string) $request['QUERY_STRING'], $queryParams);
                $householdId = (string) ($queryParams['householdId'] ?? '');
            }

            return $controller->list($householdId);
        }

        if ($this->alertRepository !== null && $method === 'PATCH' && preg_match('#^/alerts/([^/]+)/dismiss$#', $path, $matches) === 1) {
            $controller = new AlertController($this->alertRepository, $this->memberRepository);
            $id = $this->positiveId($matches[1]);
            $payload = $this->decodeJson($this->body($request));
            $householdId = (string) ($payload['householdId'] ?? '');
            if ($householdId === '') {
                throw new \InvalidArgumentException('householdId is required.');
            }
            return $controller->dismiss($id, $householdId);
        }

        if ($this->alertRepository !== null && $method === 'POST' && $path === '/alerts/dismiss-all') {
            $controller = new AlertController($this->alertRepository, $this->memberRepository);
            $payload = $this->decodeJson($this->body($request));
            $householdId = (string) ($payload['householdId'] ?? '');
            return $controller->dismissAll($householdId);
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
            $controller = new MemberController($this->memberRepository, $this->taskRepository, $this->transactionRunner);
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

        if ($this->categoryRepository !== null && $method === 'GET' && $path === '/categories') {
            return (new CategoryController($this->categoryRepository, $this->transactionRunner))->list($this->queryParam($request, 'householdId'));
        }

        if ($this->categoryRepository !== null && $method === 'POST' && $path === '/categories') {
            return (new CategoryController($this->categoryRepository))->create($this->decodeJson($this->body($request)));
        }

        if ($this->categoryRepository !== null && $method === 'PATCH' && preg_match('#^/categories/([^/]+)$#', $path, $matches) === 1) {
            $id = $this->positiveId($matches[1]);
            $payload = $this->decodeJson($this->body($request));
            return (new CategoryController($this->categoryRepository))->update($id, $this->requiredHouseholdId($request, $payload), $payload);
        }

        if ($this->categoryRepository !== null && $method === 'DELETE' && preg_match('#^/categories/([^/]+)$#', $path, $matches) === 1) {
            $id = $this->positiveId($matches[1]);
            (new CategoryController($this->categoryRepository))->delete($id, $this->requiredHouseholdId($request, $this->decodeJson($this->body($request))));
            return ['deleted' => true, 'id' => $id];
        }

        if ($this->recurrenceTypeRepository !== null && $method === 'GET' && $path === '/recurrence-types') {
            return (new RecurrenceTypeController($this->recurrenceTypeRepository, $this->transactionRunner))->list($this->queryParam($request, 'householdId'));
        }

        if ($this->recurrenceTypeRepository !== null && $method === 'POST' && $path === '/recurrence-types') {
            return (new RecurrenceTypeController($this->recurrenceTypeRepository))->create($this->decodeJson($this->body($request)));
        }

        if ($this->recurrenceTypeRepository !== null && $method === 'PATCH' && preg_match('#^/recurrence-types/([^/]+)$#', $path, $matches) === 1) {
            $id = $this->positiveId($matches[1]);
            $payload = $this->decodeJson($this->body($request));
            return (new RecurrenceTypeController($this->recurrenceTypeRepository))->update($id, $this->requiredHouseholdId($request, $payload), $payload);
        }

        if ($this->recurrenceTypeRepository !== null && $method === 'DELETE' && preg_match('#^/recurrence-types/([^/]+)$#', $path, $matches) === 1) {
            $id = $this->positiveId($matches[1]);
            (new RecurrenceTypeController($this->recurrenceTypeRepository))->delete($id, $this->requiredHouseholdId($request, $this->decodeJson($this->body($request))));
            return ['deleted' => true, 'id' => $id];
        }

        throw RouteNotFoundException::for($method, $path);
    }

    /** @param array<string, mixed> $request */
    private function queryParam(array $request, string $name): string
    {
        $value = (string) ($request[$name] ?? '');
        if ($value === '' && isset($request['QUERY_STRING'])) {
            parse_str((string) $request['QUERY_STRING'], $queryParams);
            $value = (string) ($queryParams[$name] ?? '');
        }

        return $value;
    }

    /**
     * Catalog writes are scoped to a household: taken from the query string, else from the JSON body.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $payload
     */
    private function requiredHouseholdId(array $request, array $payload): string
    {
        $householdId = $this->queryParam($request, 'householdId');
        if ($householdId === '') {
            $householdId = trim((string) ($payload['householdId'] ?? ''));
        }

        if ($householdId === '') {
            throw new \InvalidArgumentException('householdId is required.');
        }

        return $householdId;
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
