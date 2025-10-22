<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use PDO;
use RuntimeException;
use WeCozaEvents\Database\Connection;
use WeCozaEvents\Models\TaskCollection;

use JsonException;
use function __;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function trim;
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
        $needsPersist = false;

        if ($existing->isEmpty()) {
            $classId = $this->fetchClassIdForLog($logId);
            $previous = $this->getPreviousTasksSnapshot($classId, $logId);
            if ($previous !== null && !$previous->isEmpty()) {
                $existing = $previous;
                $needsPersist = true;
            } else {
                $existing = $this->registry->getTemplateForOperation('insert');
                $needsPersist = true;
            }
        }

        $template = $this->registry->getTemplateForOperation($operation);

        foreach ($template->all() as $task) {
            if (!$existing->has($task->getId())) {
                $existing->add($task);
                $needsPersist = true;
            }
        }

        if ($needsPersist) {
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
        $cleanNote = $note !== null ? trim($note) : null;
        $tasks = $this->getTasksWithTemplate($logId);

        if ($this->requiresNote($taskId)) {
            $orderNumber = $this->normaliseOrderNumber($cleanNote ?? '');
            if ($orderNumber === '') {
                throw new RuntimeException(__('An order number is required before completing this task.', 'wecoza-events'));
            }

            $classId = $this->fetchClassIdForLog($logId);
            $this->updateClassOrderNumber($classId, $orderNumber);
            $cleanNote = $orderNumber;
        }

        $task = $tasks->get($taskId)->markCompleted(
            $userId,
            $timestamp,
            $cleanNote === null || $cleanNote === '' ? null : $cleanNote
        );
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

    private function getPreviousTasksSnapshot(int $classId, int $currentLogId): ?TaskCollection
    {
        $table = $this->buildTableName();
        $sql = <<<SQL
SELECT tasks
FROM {$table}
WHERE class_id = :class_id
  AND log_id <> :log_id
  AND tasks IS NOT NULL
  AND jsonb_typeof(tasks) = 'array'
  AND jsonb_array_length(tasks) > 0
ORDER BY changed_at DESC, log_id DESC
LIMIT 1
SQL;

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare previous tasks lookup.');
        }

        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':log_id', $currentLogId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute previous tasks lookup.');
        }

        $payload = $stmt->fetchColumn();
        if ($payload === false || $payload === null) {
            return null;
        }

        $decoded = $this->decodeJson((string) $payload);
        if (!is_array($decoded)) {
            return null;
        }

        return TaskCollection::fromArray($decoded);
    }

    private function requiresNote(string $taskId): bool
    {
        return $taskId === 'agent-order';
    }

    private function fetchClassIdForLog(int $logId): int
    {
        $table = $this->buildTableName();
        $sql = "SELECT class_id FROM {$table} WHERE log_id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class lookup.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class lookup.');
        }

        $classId = $stmt->fetchColumn();
        if ($classId === false || $classId === null) {
            throw new RuntimeException('Unable to determine class for the supplied task.');
        }

        return (int) $classId;
    }

    private function updateClassOrderNumber(int $classId, string $orderNumber): void
    {
        $table = $this->buildClassesTableName();
        $sql = "UPDATE {$table} SET order_nr = :order_nr, updated_at = now() WHERE class_id = :class_id";

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare order number update.');
        }

        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':order_nr', $orderNumber, PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update class order number.');
        }
    }

    private function buildClassesTableName(): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->schema)) {
            throw new RuntimeException('Invalid schema name supplied.');
        }

        return sprintf('"%s".classes', $this->schema);
    }

    private function normaliseOrderNumber(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[[:cntrl:]]+/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 100) {
            $value = mb_substr($value, 0, 100);
        }

        return $value;
    }
}
