<?php
declare(strict_types=1);

namespace WeCozaEvents\Views\Presenters;

use function esc_html;
use function json_decode;
use function wp_date;

final class AISummaryPresenter
{
    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    public function present(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            $result[] = $this->presentSingle($record);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function presentSingle(array $record): array
    {
        $aiSummary = $this->parseAISummary($record['ai_summary'] ?? null);
        $operation = $this->formatOperation($record['operation'] ?? '');

        return [
            'log_id' => $record['log_id'] ?? null,
            'class_id' => $record['class_id'] ?? null,
            'class_code' => esc_html($record['class_code'] ?? ''),
            'class_subject' => esc_html($record['class_subject'] ?? ''),
            'operation' => $operation['type'],
            'operation_label' => $operation['label'],
            'operation_badge_class' => $operation['badge_class'],
            'changed_at' => $record['changed_at'] ?? '',
            'changed_at_formatted' => $this->formatDate($record['changed_at'] ?? ''),
            'has_summary' => $aiSummary !== null,
            'summary_text' => $aiSummary['summary'] ?? '',
            'summary_html' => $this->formatSummaryAsHtml($aiSummary['summary'] ?? ''),
            'summary_status' => $aiSummary['status'] ?? 'unknown',
            'summary_model' => $aiSummary['model'] ?? '',
            'tokens_used' => $aiSummary['tokens_used'] ?? null,
            'generated_at' => $aiSummary['generated_at'] ?? '',
            'summary_status_badge_class' => $this->getStatusBadgeClass($aiSummary['status'] ?? ''),
        ];
    }

    /**
     * @return array{type: string, label: string, badge_class: string}
     */
    private function formatOperation(string $operation): array
    {
        $type = strtoupper($operation);

        if ($type === 'INSERT') {
            return [
                'type' => 'INSERT',
                'label' => 'NEW CLASS',
                'badge_class' => 'badge-phoenix badge-phoenix-success',
            ];
        }

        if ($type === 'UPDATE') {
            return [
                'type' => 'UPDATE',
                'label' => 'UPDATED',
                'badge_class' => 'badge-phoenix badge-phoenix-warning',
            ];
        }

        return [
            'type' => $type,
            'label' => $type,
            'badge_class' => 'badge-phoenix badge-phoenix-secondary',
        ];
    }

    private function formatDate(string $timestamp): string
    {
        if ($timestamp === '') {
            return '';
        }

        return wp_date('F j, Y g:i a', strtotime($timestamp));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseAISummary(?string $jsonData): ?array
    {
        if ($jsonData === null || $jsonData === '') {
            return null;
        }

        $decoded = json_decode($jsonData, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function formatSummaryAsHtml(string $summary): string
    {
        if ($summary === '') {
            return '';
        }

        $lines = explode("\n", $summary);
        $html = '<ul class="list-unstyled mb-0">';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '- ') === 0) {
                $line = substr($line, 2);
            }

            $html .= '<li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>' . esc_html($line) . '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    private function getStatusBadgeClass(string $status): string
    {
        $status = strtolower($status);

        if ($status === 'success') {
            return 'badge-phoenix badge-phoenix-success';
        }

        if ($status === 'error') {
            return 'badge-phoenix badge-phoenix-danger';
        }

        if ($status === 'pending') {
            return 'badge-phoenix badge-phoenix-warning';
        }

        return 'badge-phoenix badge-phoenix-secondary';
    }
}
