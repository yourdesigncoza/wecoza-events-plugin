<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use WeCozaEvents\Database\Connection;
use WeCozaEvents\Models\ClassChangeLogRepository;

final class AISummaryDisplayService
{
    private ClassChangeLogRepository $repository;

    public function __construct(?ClassChangeLogRepository $repository = null)
    {
        $this->repository = $repository ?? new ClassChangeLogRepository();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSummaries(int $limit, ?int $classId, ?string $operation): array
    {
        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();

        return $this->repository->getLogsWithAISummary($pdo, $schema, $limit, $classId, $operation);
    }
}
