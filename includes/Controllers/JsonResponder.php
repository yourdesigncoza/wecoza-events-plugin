<?php
declare(strict_types=1);

namespace WeCozaEvents\Controllers;

use function wp_send_json_error;
use function wp_send_json_success;

final class JsonResponder
{
    public function success(array $data, int $status = 200): void
    {
        wp_send_json_success($data, $status);
    }

    public function error(string $message, int $status): void
    {
        wp_send_json_error(['message' => $message], $status);
    }
}
