<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use PDO;
use RuntimeException;
use WeCozaEvents\Database\Connection;
use WeCozaEvents\Models\TaskCollection;

use JsonException;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use const JSON_THROW_ON_ERROR;

final class TaskManager
{
    private PDO $pdo;
    private string $schema;
    private TaskTemplateRegistry $registry;

    public function __construct(?PDO $pdo = null, ?string $schema = null, ?TaskTemplateRegistry $registry = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
        $this->schema = $schema ?? Connection::getSchema();
        $this->registry = $registry ?? new TaskTemplateRegistry();
    }

    public function getTasksWithTemplate(int $logId, ?string $operation = null): TaskCollection
    {
        $operation = $operation ?? $this->fetchOperation($logId);

        $existing = $this->getTasksForLog($logId);
        $template = $this->registry->getTemplateForOperation($operation);

        if ($existing->isEmpty()) {
            if (!$template->isEmpty()) {
                $this->saveTasksForLog($logId, $template);
            }

            return $template;
        }

        $hasChanges = false;
        foreach ($template->all() as $task) {
            if (!$existing->has($task->getId())) {
                $existing->add($task);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->saveTasksForLog($logId, $existing);
        }

        return $existing;
    }

    public function markTaskCompleted(
        int $logId,
        string $taskId,
        int $userId,
        string $timestamp,
        ?string $note = null
    ): TaskCollection {
        $tasks = $this->getTasksWithTemplate($logId);

        $task = $tasks->get($taskId)->markCompleted($userId, $timestamp, $note);
        $tasks->replace($task);
        $this->saveTasksForLog($logId, $tasks);

        return $tasks;
    }

    public function reopenTask(int $logId, string $taskId): TaskCollection
    {
        $tasks = $this->getTasksWithTemplate($logId);

        $task = $tasks->get($taskId)->reopen();
        $tasks->replace($task);
        $this->saveTasksForLog($logId, $tasks);

        return $tasks;
    }

    private function fetchOperation(int $logId): string
    {
        $table = $this->buildTableName();
        $sql = "SELECT operation FROM {$table} WHERE log_id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare operation lookup.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute operation lookup.');
        }

        $operation = $stmt->fetchColumn();
        if (!is_string($operation) || $operation === '') {
            throw new RuntimeException('Unable to determine log operation.');
        }

        return $operation;
    }

    public function getTasksForLog(int $logId): TaskCollection
    {
        $table = $this->buildTableName();
        $sql = "SELECT tasks FROM {$table} WHERE log_id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare task lookup query.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute task lookup query.');
        }

        $payload = $stmt->fetchColumn();
        if ($payload === false || $payload === null) {
            return new TaskCollection();
        }

        $decoded = $this->decodeJson($payload);
        if (!is_array($decoded)) {
            return new TaskCollection();
        }

        return TaskCollection::fromArray($decoded);
    }

    public function saveTasksForLog(int $logId, TaskCollection $tasks): void
    {
        $table = $this->buildTableName();
        $sql = "UPDATE {$table} SET tasks = :tasks WHERE log_id = :id";

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare task update query.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        $stmt->bindValue(':tasks', $this->encodeJson($tasks->toArray()), PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to persist tasks payload.');
        }
    }

    private function buildTableName(): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->schema)) {
            throw new RuntimeException('Invalid schema name supplied.');
        }

        return sprintf('"%s".class_change_logs', $this->schema);
    }

    private function decodeJson(string $payload): mixed
    {
        if ($payload === '') {
            return [];
        }

        return json_decode($payload, true);
    }

    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode tasks payload: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
