<?php
declare(strict_types=1);

namespace WeCozaEvents\Support;

use function get_option;
use function is_string;
use function preg_match;
use function trim;
use function strlen;
use function str_repeat;
use function substr;

final class OpenAIConfig
{
    public const OPTION_API_KEY = 'wecoza_openai_api_key';
    public const OPTION_ENABLED = true;
    // public const OPTION_ENABLED = 'wecoza_ai_summaries_enabled';

    public function getApiKey(): ?string
    {
        $stored = get_option(self::OPTION_API_KEY, '');
        if (!is_string($stored)) {
            return null;
        }

        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        return $this->isValidApiKey($stored) ? $stored : null;
    }

    public function hasValidApiKey(): bool
    {
        return $this->getApiKey() !== null;
    }

    public function isEnabled(): bool
    // {
    //     return (bool) get_option(self::OPTION_ENABLED, false);
    // }
    {
        return self::OPTION_ENABLED;
    }
    public function isEnabledForLog(int $logId): bool
    {
        return $this->assessEligibility($logId)['eligible'];
    }

    /**
     * @return array{eligible:bool,reason:?string}
     */
    public function assessEligibility(int $logId): array
    {
        if (!$this->hasValidApiKey()) {
            return ['eligible' => false, 'reason' => 'config_missing'];
        }

        if (!$this->isEnabled()) {
            return ['eligible' => false, 'reason' => 'feature_disabled'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    public function maskApiKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = trim($key);
        if ($key === '') {
            return null;
        }

        $length = strlen($key);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($key, 0, 4) . str_repeat('*', $length - 8) . substr($key, -4);
    }

    public function isValidApiKey(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        return preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key) === 1;
    }

    public function sanitizeApiKey(string $value): string
    {
        return $this->isValidApiKey($value) ? trim($value) : '';
    }
}
