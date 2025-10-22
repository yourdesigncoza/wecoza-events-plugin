<?php
declare(strict_types=1);

namespace WeCozaEvents\Views\Presenters;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use WeCozaEvents\Models\TaskCollection;

use function __;
use function array_filter;
use function array_keys;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function get_userdata;
use function implode;
use function is_array;
use function json_decode;
use function mysql2date;
use function natcasesort;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;

final class ClassTaskPresenter
{
    /** @var array<int, string> */
    private array $userNameCache = [];

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function present(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = $this->formatClassRow($item);
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    public function collectOpenTaskLabels(array $rows): array
    {
        $labels = [];

        foreach ($rows as $row) {
            $tasks = $row['tasks']['open'] ?? [];
            if (!is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $label = trim((string) ($task['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $labels[$label] = true;
            }
        }

        $unique = array_keys($labels);
        natcasesort($unique);

        return array_values($unique);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function formatClassRow(array $item): array
    {
        $row = $item['row'] ?? [];
        $tasks = $item['tasks'] ?? new TaskCollection();

        $classId = (int) ($row['class_id'] ?? 0);
        $clientId = (int) ($row['client_id'] ?? 0);
        $clientName = trim((string) ($row['client_name'] ?? '')) ?: __('Unnamed client', 'wecoza-events');
        $type = strtoupper(trim((string) ($row['class_type'] ?? '')));
        $subject = trim((string) ($row['class_subject'] ?? ''));
        $code = trim((string) ($row['class_code'] ?? ''));
        $startDate = $this->formatDatePair((string) ($row['original_start_date'] ?? ''));
        $dueDate = $this->formatDueDate((string) ($row['delivery_date'] ?? ''), $startDate);
        $agentDisplay = $this->formatAgentDisplay($row);
        $exam = $this->formatExamLabel((bool) ($row['exam_class'] ?? false), (string) ($row['exam_type'] ?? ''));
        $seta = $this->formatSetaLabel((bool) ($row['seta_funded'] ?? false), (string) ($row['seta_name'] ?? ''));

        $logId = isset($item['log_id']) ? (int) $item['log_id'] : null;
        $operation = (string) ($item['operation'] ?? 'insert');
        $change = $this->formatChangeBadge($operation);

        if (!$tasks instanceof TaskCollection) {
            $tasks = new TaskCollection();
        }

        $tasksPayload = $this->presentTasks($tasks);
        $openCount = count($tasksPayload['open'] ?? []);
        $status = $this->formatTaskStatusBadge($openCount);

        $data = [
            'id' => $classId,
            'code' => $code,
            'client' => [
                'id' => $clientId,
                'name' => $clientName,
            ],
            'type' => $type ?: __('N/A', 'wecoza-events'),
            'subject' => $subject ?: __('No subject', 'wecoza-events'),
            'event_date' => $startDate,
            'due_date' => $dueDate,
            'agent_display' => $agentDisplay,
            'exam' => $exam,
            'status' => $status,
            'seta' => $seta,
            'change' => $change,
            'log_id' => $logId,
            'manageable' => (bool) ($item['manageable'] ?? false),
            'tasks' => $tasksPayload,
            'open_count' => $openCount,
        ];

        $data['search'] = [
            'base' => $this->buildSearchBaseString($data),
            'index' => $this->buildSearchIndexString($data),
            'status' => $this->normaliseForIndex((string) ($status['label'] ?? '')),
            'open_tokens' => $this->buildOpenTaskTokens($data),
        ];

        return $data;
    }

    private function formatAgentDisplay(array $row): string
    {
        $initialId = isset($row['initial_agent_id']) ? (int) $row['initial_agent_id'] : null;
        $primaryId = isset($row['primary_agent_id']) ? (int) $row['primary_agent_id'] : null;

        if ($initialId !== null && $initialId > 0) {
            $name = $this->formatPersonName(
                (string) ($row['initial_agent_first'] ?? ''),
                (string) ($row['initial_agent_surname'] ?? ''),
                (string) ($row['initial_agent_initials'] ?? '')
            );
            return sprintf(__('%1$s · %2$s', 'wecoza-events'), $initialId, $name);
        }

        if ($primaryId !== null && $primaryId > 0) {
            $name = $this->formatPersonName(
                (string) ($row['primary_agent_first'] ?? ''),
                (string) ($row['primary_agent_surname'] ?? ''),
                (string) ($row['primary_agent_initials'] ?? '')
            );
            return sprintf(__('Primary: %1$s · %2$s', 'wecoza-events'), '#' . $primaryId, $name);
        }

        return __('No agent assigned', 'wecoza-events');
    }

    private function formatPersonName(string $first, string $surname, string $initials): string
    {
        $parts = array_filter([
            trim($first),
            trim($initials),
            trim($surname),
        ], static fn (string $value): bool => $value !== '');

        return $parts !== [] ? implode(' ', $parts) : __('Unnamed agent', 'wecoza-events');
    }

    private function formatExamLabel(bool $isExam, string $examType): array
    {
        if ($isExam) {
            $label = __('Exam Class', 'wecoza-events');
            if ($examType !== '') {
                $label = sprintf('%s', $label);
            }

            return [
                'label' => $label,
                'class' => 'badge-phoenix-success',
            ];
        }

        return [
            'label' => __('Not Exam', 'wecoza-events'),
            'class' => 'badge-phoenix-secondary',
        ];
    }

    private function formatSetaLabel(bool $funded, string $name): array
    {
        if ($funded) {
            $label = $name !== '' ? sprintf('%s', $name) : __('SETA Funded', 'wecoza-events');

            return [
                'label' => $label,
                'class' => 'badge-phoenix-success',
            ];
        }

        return [
            'label' => __('Not SETA', 'wecoza-events'),
            'class' => 'badge-phoenix-secondary',
        ];
    }

    private function formatTaskStatusBadge(int $openCount): array
    {
        if ($openCount > 0) {
            return [
                'label' => sprintf(__('Open +%d', 'wecoza-events'), $openCount),
                'class' => 'badge-phoenix-warning',
            ];
        }

        return [
            'label' => strtoupper(__('Completed', 'wecoza-events')),
            'class' => 'badge-phoenix-secondary',
        ];
    }

    private function formatChangeBadge(string $operation): array
    {
        $value = strtolower(trim($operation));

        return match ($value) {
            'insert' => [
                'value' => 'insert',
                'label' => strtoupper(__('New', 'wecoza-events')),
                'class' => 'badge-phoenix-success',
            ],
            'update' => [
                'value' => 'update',
                'label' => strtoupper(__('Update', 'wecoza-events')),
                'class' => 'badge-phoenix-primary',
            ],
            default => [
                'value' => $value !== '' ? $value : 'unknown',
                'label' => strtoupper($value !== '' ? ucfirst($value) : __('Unknown', 'wecoza-events')),
                'class' => 'badge-phoenix-secondary',
            ],
        };
    }

    /**
     * @param array{id:string|int,code:string,subject:string,type:string,client:array<string, mixed>,agent_display:string,seta:array<string,string>,change:array<string,string>} $class
     */
    private function buildSearchBaseString(array $class): string
    {
        return implode(' ', $this->buildSearchBaseParts($class));
    }

    /**
     * @param array<string, mixed> $class
     * @return array<int, string>
     */
    private function buildSearchBaseParts(array $class): array
    {
        $parts = [];
        $parts[] = (string) ($class['id'] ?? '');
        $parts[] = (string) ($class['code'] ?? '');
        $parts[] = (string) ($class['subject'] ?? '');
        $parts[] = (string) ($class['type'] ?? '');
        $parts[] = (string) ($class['client']['id'] ?? '');
        $parts[] = (string) ($class['client']['name'] ?? '');
        $parts[] = (string) ($class['agent_display'] ?? '');
        $parts[] = (string) ($class['seta']['label'] ?? '');
        $parts[] = (string) ($class['change']['label'] ?? '');
        $parts[] = (string) ($class['change']['value'] ?? '');

        $parts = array_filter(array_map([$this, 'normaliseForIndex'], $parts), static fn (string $value): bool => $value !== '');

        return array_values(array_unique($parts));
    }

    /**
     * @param array<string, mixed> $class
     */
    private function buildSearchIndexString(array $class): string
    {
        $baseParts = $this->buildSearchBaseParts($class);
        $tokens = $baseParts;

        $openTasks = $class['tasks']['open'] ?? [];
        if (is_array($openTasks)) {
            foreach ($openTasks as $task) {
                if (is_array($task) && isset($task['label'])) {
                    $token = $this->normaliseForIndex((string) $task['label']);
                    if ($token !== '') {
                        $tokens[] = $token;
                    }
                }
            }
        }

        if (isset($class['status']['label'])) {
            $statusToken = $this->normaliseForIndex((string) $class['status']['label']);
            if ($statusToken !== '') {
                $tokens[] = $statusToken;
            }
        }

        if ($tokens === []) {
            return '';
        }

        $tokens = array_values(array_unique($tokens));

        return implode(' ', $tokens);
    }

    /**
     * @param array<string, mixed> $class
     */
    private function buildOpenTaskTokens(array $class): string
    {
        $openTasks = $class['tasks']['open'] ?? [];
        if (!is_array($openTasks) || $openTasks === []) {
            return '';
        }

        $tokens = [];
        foreach ($openTasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $token = $this->normaliseForToken((string) ($task['label'] ?? ''));
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        if ($tokens === []) {
            return '';
        }

        $tokens = array_values(array_unique($tokens));

        return implode('|', $tokens);
    }

    private function normaliseForToken(string $value): string
    {
        return $this->normaliseForIndex($value);
    }

    private function normaliseForIndex(string $value): string
    {
        $value = str_replace('|', ' ', strtolower(trim($value)));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value === null ? '' : $value;
    }

    private function formatDueDate(string $rawDelivery, array $startDate): array
    {
        if ($rawDelivery !== '') {
            return $this->formatDatePair($rawDelivery);
        }

        return $startDate['iso'] !== '' ? $startDate : ['iso' => '', 'human' => ''];
    }

    private function formatDatePair(string $timestamp): array
    {
        if ($timestamp === '') {
            return ['iso' => '', 'human' => __('No date', 'wecoza-events')];
        }

        try {
            $dt = new DateTimeImmutable($timestamp);
            return [
                'iso' => $dt->format(DateTimeInterface::ATOM),
                'human' => $dt->format('M j, Y'),
            ];
        } catch (Exception $exception) {
            return ['iso' => $timestamp, 'human' => $timestamp];
        }
    }

    public function presentTasks(TaskCollection $tasks): array
    {
        $open = [];
        $completed = [];

        foreach ($tasks->all() as $task) {
            $payload = [
                'id' => $task->getId(),
                'label' => $task->getLabel(),
            ];

            if ($task->isCompleted()) {
                $payload['completed_by'] = $this->resolveUserName($task->getCompletedBy());
                $payload['completed_at'] = $this->formatCompletedAt($task->getCompletedAt());
                $payload['note'] = $task->getNote();
                $payload['reopen_label'] = __('Reopen', 'wecoza-events');
                $completed[] = $payload;
            } else {
                $isAgentOrderTask = $task->getId() === 'agent-order';
                if ($isAgentOrderTask) {
                    $payload['note_label'] = __('Order number', 'wecoza-events');
                    $payload['note_placeholder'] = __('Order Number Required', 'wecoza-events');
                    $payload['note_required'] = true;
                    $payload['note_required_message'] = __(
                        'Enter the agent order number before completing this task.',
                        'wecoza-events'
                    );
                } else {
                    $payload['note_label'] = __('Completion note', 'wecoza-events');
                    $payload['note_placeholder'] = __('Note (optional)', 'wecoza-events');
                    $payload['note_required'] = false;
                }

                $payload['complete_label'] = __('Complete', 'wecoza-events');
                $open[] = $payload;
            }
        }

        return [
            'open' => $open,
            'completed' => $completed,
        ];
    }

    private function resolveUserName(?int $userId): string
    {
        if ($userId === null || $userId <= 0) {
            return __('Unknown user', 'wecoza-events');
        }

        if (isset($this->userNameCache[$userId])) {
            return $this->userNameCache[$userId];
        }

        $user = get_userdata($userId);
        $name = $user?->display_name ?? $user?->user_login ?? __('Unknown user', 'wecoza-events');
        $this->userNameCache[$userId] = $name;

        return $name;
    }

    private function formatCompletedAt(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return __('Unknown time', 'wecoza-events');
        }

        return mysql2date('M j, Y H:i', $timestamp, true);
    }
}
