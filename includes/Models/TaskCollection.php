<?php
declare(strict_types=1);

namespace WeCozaEvents\Models;

use InvalidArgumentException;

final class TaskCollection
{
    /** @var array<string, Task> */
    private array $tasks = [];

    /**
     * @param array<int, Task> $tasks
     */
    public function __construct(array $tasks = [])
    {
        foreach ($tasks as $task) {
            $this->add($task);
        }
    }

    public static function fromArray(array $payload): self
    {
        $collection = new self();
        foreach ($payload as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $collection->add(Task::fromArray($item));
        }

        return $collection;
    }

    public function add(Task $task): void
    {
        $id = $task->getId();
        if ($id === '') {
            throw new InvalidArgumentException('Task id cannot be empty.');
        }

        $this->tasks[$id] = $task;
    }

    public function has(string $taskId): bool
    {
        return isset($this->tasks[$taskId]);
    }

    public function get(string $taskId): Task
    {
        if (!$this->has($taskId)) {
            throw new InvalidArgumentException('Unknown task id: ' . $taskId);
        }

        return $this->tasks[$taskId];
    }

    public function replace(Task $task): void
    {
        $this->tasks[$task->getId()] = $task;
    }

    /**
     * @return array<int, Task>
     */
    public function all(): array
    {
        return array_values($this->tasks);
    }

    public function isEmpty(): bool
    {
        return $this->tasks === [];
    }

    /**
     * @return array<int, Task>
     */
    public function open(): array
    {
        return array_values(array_filter($this->tasks, static fn (Task $task): bool => !$task->isCompleted()));
    }

    /**
     * @return array<int, Task>
     */
    public function completed(): array
    {
        return array_values(array_filter($this->tasks, static fn (Task $task): bool => $task->isCompleted()));
    }

    public function toArray(): array
    {
        return array_map(static fn (Task $task): array => $task->toArray(), $this->all());
    }
}
