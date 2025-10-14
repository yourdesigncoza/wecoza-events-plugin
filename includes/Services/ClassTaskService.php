<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use RuntimeException;
use WeCozaEvents\Models\ClassTaskRepository;
use WeCozaEvents\Models\TaskCollection;

use function count;
use function strtolower;

final class ClassTaskService
{
    private ClassTaskRepository $repository;
    private TaskManager $taskManager;
    private TaskTemplateRegistry $templateRegistry;

    public function __construct(
        ?ClassTaskRepository $repository = null,
        ?TaskManager $taskManager = null,
        ?TaskTemplateRegistry $templateRegistry = null
    ) {
        $this->repository = $repository ?? new ClassTaskRepository();
        $this->taskManager = $taskManager ?? new TaskManager();
        $this->templateRegistry = $templateRegistry ?? new TaskTemplateRegistry();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClassTasks(int $limit, string $sortDirection, bool $prioritiseOpen, ?int $classIdFilter): array
    {
        $rows = $this->repository->fetchClasses($limit, $sortDirection, $classIdFilter);

        $items = [];
        foreach ($rows as $row) {
            $logId = isset($row['log_id']) ? (int) $row['log_id'] : null;
            $operation = strtolower((string) ($row['operation'] ?? 'insert')) ?: 'insert';

            if ($logId === null || $logId <= 0) {
                $tasks = $this->templateRegistry->getTemplateForOperation($operation);
                $manageable = false;
            } else {
                $tasks = $this->taskManager->getTasksWithTemplate($logId, $operation);
                $manageable = true;
            }

            if (!$tasks instanceof TaskCollection) {
                throw new RuntimeException('Invalid tasks payload.');
            }

            $items[] = [
                'row' => $row,
                'tasks' => $tasks,
                'log_id' => $logId,
                'operation' => $operation,
                'manageable' => $manageable,
                'open_count' => count($tasks->open()),
            ];
        }

        if ($prioritiseOpen) {
            [$open, $completed] = $this->partitionByOpenCount($items);
            $items = [...$open, ...$completed];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function partitionByOpenCount(array $items): array
    {
        $open = [];
        $completed = [];

        foreach ($items as $item) {
            if (($item['open_count'] ?? 0) > 0) {
                $open[] = $item;
            } else {
                $completed[] = $item;
            }
        }

        return [$open, $completed];
    }
}
