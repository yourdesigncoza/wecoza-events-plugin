<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function strtolower;
use function sprintf;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class PayloadFormatter
{
    /**
     * @return array<string, mixed>|null
     */
    public function decodePayload(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function formatLogLine(array $payload): string
    {
        $timestamp = $payload['changed_at'] ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
        $operation = strtolower((string) ($payload['operation'] ?? 'unknown'));
        $classId = $payload['class_id'] ?? 'n/a';
        $classCode = $payload['class_code'] ?? 'n/a';
        $classSubject = $payload['class_subject'] ?? 'n/a';
        $diff = $payload['diff'] ?? [];

        if (!is_array($diff)) {
            $diff = (array) $diff;
        }

        $diffJson = json_encode($diff, JSON_UNESCAPED_SLASHES);
        if ($diffJson === false) {
            $diffJson = '{}';
        }

        return sprintf('[%s] %s class_id=%s code=%s subject=%s diff=%s', (string) $timestamp, (string) $operation, (string) $classId, (string) $classCode, (string) $classSubject, $diffJson);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeDatabasePayload(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [];
        }

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [];
        }

        return $decoded ?? [];
    }
}
