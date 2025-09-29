<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use PDO;
use PDOException;
use WeCozaEvents\Views\ConsoleView;
use function file_put_contents;
use function sleep;
use const FILE_APPEND;
use const LOCK_EX;

final class ClassChangeListener
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PayloadFormatter $formatter,
        private readonly ConsoleView $view,
        private readonly ?string $logFile
    ) {
    }

    public function listen(): void
    {
        $this->pdo->exec('LISTEN class_change_channel;');
        if ($this->logFile === null) {
            $this->view->info('Listening for class changes. Log file output disabled.');
        } else {
            $this->view->info('Listening for class changes. Logging to ' . $this->logFile);
        }

        while (true) {
            try {
                $notification = $this->pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 5000);
            } catch (PDOException $exception) {
                $this->view->error('LISTEN/NOTIFY failed: ' . $exception->getMessage());
                sleep(2);
                continue;
            }

            if ($notification === false) {
                $this->keepConnectionAlive();
                continue;
            }

            $payload = $this->formatter->decodePayload($notification['payload'] ?? null);
            if ($payload === null) {
                continue;
            }

            $line = $this->formatter->formatLogLine($payload);
            if ($this->appendToLog($line)) {
                $this->view->info($line);
            }
        }
    }

    private function keepConnectionAlive(): void
    {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $exception) {
            $this->view->error('Connection keep-alive failed: ' . $exception->getMessage());
            sleep(2);
        }
    }

    private function appendToLog(string $line): bool
    {
        if ($this->logFile === null) {
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
