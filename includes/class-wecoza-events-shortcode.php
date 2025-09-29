<?php
namespace WeCozaEvents\Shortcodes;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use RuntimeException;
use WeCozaEvents\Database\Connection;

use function __;
use function add_shortcode;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function preg_match;
use function shortcode_atts;
use function sprintf;
use function strtolower;
use function ucfirst;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class AgentLogsShortcode
{
    private const DEFAULT_LIMIT = 50;
    private static bool $stylesPrinted = false;

    public static function register(): void
    {
        add_shortcode('wecoza_agent_logs', [self::class, 'render']);
    }

    public static function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
        ], $atts, $tag);

        $limit = is_numeric($atts['limit']) ? max(1, (int) $atts['limit']) : self::DEFAULT_LIMIT;

        $messages = [];
        $entries = [];

        try {
            $entries = self::getDatabaseEntries($limit);
        } catch (RuntimeException $exception) {
            $messages[] = sprintf(
                esc_html__('Database log retrieval failed: %s', 'wecoza-events'),
                esc_html($exception->getMessage())
            );
        }

        if (empty($entries)) {
            $message = $messages[0] ?? esc_html__('No log entries available.', 'wecoza-events');
            return self::wrapMessage($message);
        }

        return self::renderEntries($entries, $messages);
    }

    private static function wrapMessage(string $message): string
    {
        return '<div class="wecoza-agent-logs-message">' . $message . '</div>';
    }

    /**
     * @param array<int, array<string, string>> $entries
     * @param array<int, string>                $messages
     */
    private static function renderEntries(array $entries, array $messages): string
    {
        ob_start();

        self::maybePrintStyles();
        ?>
        <div class="wecoza-agent-logs">
            <?php if (!empty($messages)): ?>
                <div class="wecoza-agent-logs-notice">
                    <?php foreach ($messages as $message): ?>
                        <p><?php echo esc_html($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="wecoza-agent-logs-list">
                <?php foreach ($entries as $entry): ?>
                    <?php [$timestampIso, $timestampHuman] = self::formatTimestampPair($entry['timestamp']); ?>
                    <?php $operationLabel = self::formatOperationLabel($entry['operation']); ?>
                    <article class="wecoza-agent-logs-item">
                        <header class="wecoza-agent-logs-item__header">
                            <span class="wecoza-agent-logs-item__operation <?php echo esc_html(self::operationAccentClass($entry['operation'])); ?>">
                                <?php echo esc_html($operationLabel); ?>
                            </span>
                            <?php if ($entry['code'] !== ''): ?>
                                <span class="wecoza-agent-logs-item__code"><?php echo esc_html($entry['code']); ?></span>
                            <?php endif; ?>
                            <?php if ($entry['subject'] !== ''): ?>
                                <span class="wecoza-agent-logs-item__subject"><?php echo esc_html($entry['subject']); ?></span>
                            <?php endif; ?>
                        </header>
                        <div class="wecoza-agent-logs-item__meta">
                            <span class="wecoza-agent-logs-item__timestamp">
                                <time datetime="<?php echo esc_attr($timestampIso); ?>"><?php echo esc_html($timestampHuman); ?></time>
                            </span>
                            <?php if ($entry['class_id'] !== ''): ?>
                                <span class="wecoza-agent-logs-item__id">
                                    <?php echo esc_html(sprintf(__('Class ID: %s', 'wecoza-events'), $entry['class_id'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($entry['diff'] !== ''): ?>
                            <details class="wecoza-agent-logs-item__diff">
                                <summary><?php echo esc_html__('View changes', 'wecoza-events'); ?></summary>
                                <pre><?php echo esc_html($entry['diff']); ?></pre>
                            </details>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }

    private static function formatTimestampPair(string $timestamp): array
    {
        try {
            $dt = new DateTimeImmutable($timestamp);
            return [
                $dt->format(DateTimeInterface::ATOM),
                $dt->format('M j, Y H:i T'),
            ];
        } catch (Exception $exception) {
            return [$timestamp, $timestamp];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function getDatabaseEntries(int $limit): array
    {
        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
            throw new RuntimeException('Invalid schema name.');
        }

        $table = sprintf('"%s".class_change_logs', $schema);
        $sql = <<<SQL
SELECT
    operation,
    changed_at,
    class_id,
    (new_row->>'class_code') AS class_code,
    (new_row->>'class_subject') AS class_subject,
    diff
FROM {$table}
ORDER BY changed_at DESC
LIMIT :limit;
SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $entries = [];
        while ($row = $stmt->fetch()) {
            $diff = self::normaliseDiff($row['diff'] ?? null);

            $entries[] = [
                'timestamp' => $row['changed_at'] ?? '',
                'operation' => strtolower((string) ($row['operation'] ?? '')),
                'class_id' => (string) ($row['class_id'] ?? ''),
                'code' => (string) ($row['class_code'] ?? ''),
                'subject' => (string) ($row['class_subject'] ?? ''),
                'diff' => $diff,
            ];
        }

        return $entries;
    }

    /**
     * @param mixed $diff
     */
    private static function normaliseDiff($diff): string
    {
        if (is_array($diff)) {
            return json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (is_string($diff)) {
            $decoded = json_decode($diff, true);
            if (is_array($decoded)) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            }

            return $diff;
        }

        return '';
    }

    private static function maybePrintStyles(): void
    {
        if (self::$stylesPrinted) {
            return;
        }

        self::$stylesPrinted = true;
        ?>
        <style>
            .wecoza-agent-logs {
                margin: 1rem 0;
                font-family: inherit;
            }

            .wecoza-agent-logs-list {
                display: grid;
                gap: 1rem;
            }

            .wecoza-agent-logs-notice {
                background: #fef3c7;
                border: 1px solid #facc15;
                border-radius: 4px;
                color: #7c4a03;
                padding: 0.75rem;
                margin-bottom: 1rem;
            }

            .wecoza-agent-logs-item {
                border: 1px solid #d8dee9;
                border-radius: 6px;
                padding: 1rem;
                background: #ffffff;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            }

            .wecoza-agent-logs-item__header {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .wecoza-agent-logs-item__operation {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.25rem 0.6rem;
                border-radius: 999px;
                font-weight: 600;
                font-size: 0.75rem;
                letter-spacing: 0.03em;
                text-transform: uppercase;
            }

            .wecoza-agent-logs-item__operation.is-insert {
                background: #ecfdf5;
                color: #047857;
            }

            .wecoza-agent-logs-item__operation.is-update {
                background: #eff6ff;
                color: #1d4ed8;
            }

            .wecoza-agent-logs-item__operation.is-delete {
                background: #fef2f2;
                color: #b91c1c;
            }

            .wecoza-agent-logs-item__operation.is-generic {
                background: #f1f5f9;
                color: #475569;
            }

            .wecoza-agent-logs-item__code {
                font-weight: 600;
                color: #1f2937;
            }

            .wecoza-agent-logs-item__subject {
                color: #4b5563;
            }

            .wecoza-agent-logs-item__meta {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                font-size: 0.85rem;
                color: #475569;
                margin-bottom: 0.75rem;
            }

            .wecoza-agent-logs-item__diff summary {
                cursor: pointer;
                color: #2563eb;
                font-weight: 600;
            }

            .wecoza-agent-logs-item__diff pre {
                margin-top: 0.75rem;
                padding: 0.75rem;
                background: #0f172a;
                color: #e2e8f0;
                border-radius: 4px;
                overflow-x: auto;
                font-size: 0.85rem;
                line-height: 1.4;
            }

            @media (min-width: 992px) {
                .wecoza-agent-logs-list {
                    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
                }
            }
        </style>
        <?php
    }

    private static function formatOperationLabel(string $operation): string
    {
        $operation = strtolower($operation);
        return ucfirst($operation);
    }

    private static function operationAccentClass(string $operation): string
    {
        return match (strtolower($operation)) {
            'insert' => 'is-insert',
            'update' => 'is-update',
            'delete' => 'is-delete',
            default => 'is-generic',
        };
    }
}
