<?php
declare(strict_types=1);

namespace WeCozaEvents\Views;

use const STDERR;
use const STDOUT;
use function fwrite;

final class ConsoleView
{
    public function info(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
