<?php
declare(strict_types=1);

namespace WeCozaEvents\Views\Presenters;

use function esc_html;
use function file_exists;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;
use function wp_json_encode;
use function wp_strip_all_tags;
use const JSON_PRETTY_PRINT;

final class NotificationEmailPresenter
{
    /**
     * @param array<string,mixed> $context
     * @return array{subject:string,body:string,headers:array<int,string>}
     */
    public function present(array $context): array
    {
        $operation = strtoupper((string) ($context['operation'] ?? ''));
        $newRow = $context['new_row'] ?? [];
        $classId = (string) ($context['row']['class_id'] ?? '');
        $classCode = (string) ($newRow['class_code'] ?? '');

        $subject = sprintf('[WeCoza] Class %s: %s (%s)', strtolower($operation), $classId, $classCode !== '' ? $classCode : 'no-code');

        $html = $this->renderHtml($context);
        $plain = trim(wp_strip_all_tags($html));

        $body = $html;
        
        // $body = $html . "\n\n<!-- Plain text fallback -->\n<pre style=\"font-family: monospace; white-space: pre-wrap;\">" . esc_html($plain) . '</pre>';

        return [
            'subject' => $subject,
            'body' => $body,
            'headers' => [
                'Content-Type: text/html; charset=UTF-8',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function renderHtml(array $context): string
    {
        $template = WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/event-tasks/email-summary.php';
        if (!file_exists($template)) {
            return wp_json_encode($context, JSON_PRETTY_PRINT);
        }

        ob_start();
        $payload = $context;
        include $template;
        $output = ob_get_clean();

        return is_string($output) ? $output : '';
    }
}
