<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use function get_option;
use function is_email;
use function is_string;
use function trim;

final class NotificationSettings
{
    public function getRecipientForOperation(string $operation): ?string
    {
        $operation = strtoupper($operation);

        return match ($operation) {
            'INSERT' => $this->resolve(['wecoza_notification_class_created']),
            'UPDATE' => $this->resolve(['wecoza_notification_class_updated']),
            default => null,
        };
    }

    /**
     * @param array<int, string> $optionKeys
     */
    private function resolve(array $optionKeys): ?string
    {
        foreach ($optionKeys as $optionKey) {
            $option = get_option($optionKey, '');
            if (is_string($option)) {
                $option = trim($option);
                if ($option !== '' && is_email($option)) {
                    return $option;
                }
            }
        }

        return null;
    }
}
