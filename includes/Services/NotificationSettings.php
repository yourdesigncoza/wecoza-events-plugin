<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use function constant;
use function defined;
use function function_exists;
use function getenv;
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
            'INSERT' => $this->resolve('WECOZA_NOTIFY_INSERT_EMAIL', 'wecoza_notify_insert_email', 'WECOZA_NOTIFY_INSERT_EMAIL'),
            'UPDATE' => $this->resolve('WECOZA_NOTIFY_UPDATE_EMAIL', 'wecoza_notify_update_email', 'WECOZA_NOTIFY_UPDATE_EMAIL'),
            default => null,
        };
    }

    private function resolve(string $envKey, string $optionKey, string $constantKey): ?string
    {
        $env = getenv($envKey);
        if ($env !== false) {
            $address = trim($env);
            if ($address !== '' && is_email($address)) {
                return $address;
            }
        }

        if (defined($constantKey)) {
            $value = constant($constantKey);
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '' && is_email($value)) {
                    return $value;
                }
            }
        }

        if (function_exists('get_option')) {
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
