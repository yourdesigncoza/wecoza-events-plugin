<?php
declare(strict_types=1);

namespace WeCozaEvents\Views;

use RuntimeException;

use function extract;
use function file_exists;
use function ob_get_clean;
use function ob_start;
use function str_replace;

final class TemplateRenderer
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? __DIR__;
    }

    public function render(string $template, array $data = []): string
    {
        $path = $this->resolvePath($template);

        if (!file_exists($path)) {
            throw new RuntimeException('Template not found: ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        $relative = str_replace(['::', '.'], ['/', '/'], $template);

        return $this->basePath . '/' . $relative . '.php';
    }
}
