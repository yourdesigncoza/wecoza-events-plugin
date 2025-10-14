<?php
declare(strict_types=1);

namespace WeCozaEvents\Support;

use function absint;
use function is_string;
use function sanitize_text_field;
use function wp_unslash;

final class WordPressRequest
{
    public function getQueryString(string $key, ?string $default = null): ?string
    {
        if (!isset($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return $default;
        }

        $raw = wp_unslash($_GET[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (!is_string($raw)) {
            return $default;
        }

        $value = sanitize_text_field($raw);

        return $value === '' ? $default : $value;
    }

    public function getQueryInt(string $key): ?int
    {
        $value = $this->getQueryString($key);
        if ($value === null) {
            return null;
        }

        $int = absint($value);

        return $int > 0 ? $int : null;
    }

    public function getPostString(string $key, ?string $default = null): ?string
    {
        if (!isset($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return $default;
        }

        $raw = wp_unslash($_POST[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if (!is_string($raw)) {
            return $default;
        }

        $value = sanitize_text_field($raw);

        return $value === '' ? $default : $value;
    }

    public function getPostInt(string $key): ?int
    {
        $value = $this->getPostString($key);
        if ($value === null) {
            return null;
        }

        $int = absint($value);

        return $int > 0 ? $int : null;
    }
}
