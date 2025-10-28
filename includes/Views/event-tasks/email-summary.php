<?php
$payload = $payload ?? [];
$row = $payload['row'] ?? [];
$summary = $payload['summary'] ?? [];
$status = strtolower((string) ($summary['status'] ?? 'pending'));
$summaryText = trim((string) ($summary['summary'] ?? ''));
$emailContext = $payload['email_context'] ?? [];
$aliasMap = $emailContext['alias_map'] ?? [];
$metadata = [
    'Operation' => $payload['operation'] ?? '',
    'Changed At' => $row['changed_at'] ?? '',
    'Class ID' => $row['class_id'] ?? '',
    'Class Code' => $payload['new_row']['class_code'] ?? '',
    'Class Subject' => $payload['new_row']['class_subject'] ?? '',
];
$metrics = [
    'Status' => $summary['status'] ?? 'pending',
    'Model' => $summary['model'] ?? 'n/a',
    'Tokens Used' => $summary['tokens_used'] ?? 0,
    'Processing Time (ms)' => $summary['processing_time_ms'] ?? 0,
    'Attempts' => $summary['attempts'] ?? 0,
    'Error Code' => $summary['error_code'] ?? null,
    'Error Message' => $summary['error_message'] ?? null,
];
// $diffJson = wp_json_encode($payload['diff'] ?? [], JSON_PRETTY_PRINT);
// $newRowJson = wp_json_encode($payload['new_row'] ?? [], JSON_PRETTY_PRINT);
// $oldRowJson = wp_json_encode($payload['old_row'] ?? [], JSON_PRETTY_PRINT);
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2933;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">WeCoza Class Change Notification</h1>

        <section style="margin-bottom: 20px;">
        <h2 style="font-size: 16px; margin-bottom: 8px;">Change Metadata</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <?php foreach ($metadata as $label => $value): ?>
                <tr>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;"><?php echo esc_html($label); ?></th>
                    <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html((string) $value); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>

    <section style="margin-bottom: 20px;">
        <h2 style="font-size: 16px; margin-bottom: 8px;">AI Summary</h2>
        <?php if ($status === 'success' && $summaryText !== ''): ?>
            <div style="background: #f3f4f6; padding: 12px; border-radius: 6px;">
                <?php echo nl2br(esc_html($summaryText)); ?>
            </div>
        <?php else: ?>
            <p style="margin: 0 0 12px 0;">Summary unavailable (status: <?php echo esc_html($summary['status'] ?? 'pending'); ?>). Raw audit payloads are included below.</p>
            <?php if (!empty($summary['error_message'])): ?>
                <p style="margin: 0; color: #b91c1c;">Reason: <?php echo esc_html((string) $summary['error_message']); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($aliasMap) && is_array($aliasMap)): ?>
            <h3 style="font-size: 15px; margin: 16px 0 8px 0;">Learner Alias Mapping</h3>
            <ul style="margin: 0; padding-left: 18px;">
                <?php foreach ($aliasMap as $alias => $real): ?>
                    <li><?php echo esc_html($alias); ?> &rarr; <?php echo esc_html($real); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section style="margin-bottom: 20px;">
        <h2 style="font-size: 16px; margin-bottom: 8px;">AI Generation Details</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <?php foreach ($metrics as $label => $value): ?>
                <?php if ($value === null || $value === '') { continue; } ?>
                <tr>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;"><?php echo esc_html((string) $label); ?></th>
                    <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html((string) $value); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>

    <p style="font-size: 12px; color: #6b7280;">If the summary looks incorrect, reply with the log ID and we will investigate.</p>
</div>
