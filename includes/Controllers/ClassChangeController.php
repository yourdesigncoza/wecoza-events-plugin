<?php
declare(strict_types=1);

namespace WeCozaEvents\Controllers;

use PDO;
use WeCozaEvents\Models\ClassChangeLogRepository;
use WeCozaEvents\Models\ClassChangeSchema;
use WeCozaEvents\Services\ClassChangeListener;
use WeCozaEvents\Services\PayloadFormatter;
use WeCozaEvents\Views\ConsoleView;
use function file_put_contents;
use function sprintf;
use const FILE_APPEND;
use const LOCK_EX;

final class ClassChangeController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $schema,
        private readonly ?string $logFile,
        private readonly ConsoleView $view,
        private readonly ClassChangeSchema $schemaManager,
        private readonly ClassChangeLogRepository $repository,
        private readonly ClassChangeListener $listener,
        private readonly PayloadFormatter $formatter
    ) {
    }

    public function install(): void
    {
        $this->ensureArtifacts();
        if ($this->logFile === null) {
            $this->view->info('Class change trigger installed. Log file output disabled.');
        } else {
            $this->view->info('Class change trigger installed.');
        }
    }

    public function dump(): void
    {
        $this->ensureArtifacts();
        $written = 0;

        $this->repository->exportLogs($this->pdo, $this->schema, $this->formatter, function (array $payload) use (&$written): void {
            $line = $this->formatter->formatLogLine($payload);
            if ($this->appendLine($line)) {
                $written++;
            }
        });

        if ($this->logFile === null) {
            $this->view->info(sprintf('Existing log entries output to console (%d lines).', $written));
        } else {
            $this->view->info(sprintf('Existing log entries appended to %s (%d lines).', (string) $this->logFile, $written));
        }
    }

    public function listen(): void
    {
        $this->ensureArtifacts();
        $this->listener->listen();
    }

    private function ensureArtifacts(): void
    {
        $this->schemaManager->ensureArtifacts($this->pdo, $this->schema);
    }

    private function appendLine(string $line): bool
    {
        if ($this->logFile === null) {
            $this->view->info($line);
            return true;
        }

        $result = file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            $this->view->error('Failed to write to log file: ' . $this->logFile);
            return false;
        }

        return true;
    }
}
