<?php
declare(strict_types=1);

namespace WeCozaEvents\Services\AISummaryService\Traits;

use WeCozaEvents\Support\FieldMapper;

use function array_key_exists;
use function array_merge;
use function array_reverse;
use function chr;
use function count;
use function explode;
use function implode;
use function intdiv;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function ord;
use function preg_replace;
use function str_contains;
use function strlen;
use function str_repeat;
use function strtolower;
use function substr;
use function trim;

trait DataObfuscator
{
    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int}|null $state
     * @return array{payload:array<string|int,mixed>, mappings:array<string,string>, state:array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int}}
     */
    private function obfuscatePayload(array $payload, ?array &$state = null): array
    {
        if ($state === null) {
            $state = $this->initialState();
        }

        $obfuscated = $this->obfuscateNode($payload, $state, null);

        return [
            'payload' => $obfuscated,
            'mappings' => $state['aliases'],
            'state' => $state,
        ];
    }

    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int}|null $state
     * @return array{payload:array<string|int,mixed>, mappings:array<string,string>, field_labels:array<string,string>, state:array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int}}
     */
    private function obfuscatePayloadWithLabels(array $payload, ?array &$state = null): array
    {
        $result = $this->obfuscatePayload($payload, $state);

        $labeledPayload = $this->applyFieldLabels($result['payload']);
        $fieldLabels = $this->extractFieldLabels($result['payload']);

        return [
            'payload' => $labeledPayload,
            'mappings' => $result['mappings'],
            'field_labels' => $fieldLabels,
            'state' => $result['state'],
        ];
    }

    /**
     * @param array<string|int,mixed> $payload
     * @return array<string|int,mixed>
     */
    private function applyFieldLabels(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $label = is_string($key) ? FieldMapper::getLabel($key) : $key;

            if (is_array($value)) {
                $result[$label] = $this->applyFieldLabels($value);
            } else {
                $result[$label] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string|int,mixed> $payload
     * @return array<string,string>
     */
    private function extractFieldLabels(array $payload): array
    {
        $labels = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $label = FieldMapper::getLabel($key);
                $labels[$label] = $key;
            }

            if (is_array($value)) {
                $labels = array_merge($labels, $this->extractFieldLabels($value));
            }
        }

        return $labels;
    }

    /**
     * @return array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int}
     */
    private function initialState(): array
    {
        return [
            'nameCounter' => 0,
            'aliases' => [],
            'reverse' => [],
        ];
    }

    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int} $state
     * @param array<string|int,mixed> $node
     * @return array<string|int,mixed>
     */
    private function obfuscateNode(array $node, array &$state, ?string $parentKey): array
    {
        $result = [];

        foreach ($node as $key => $value) {
            $result[$key] = $this->obfuscateValue($key, $value, $state, $parentKey);
        }

        return $result;
    }

    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int} $state
     */
    private function obfuscateValue(string|int $key, mixed $value, array &$state, ?string $parentKey): mixed
    {
        if (is_array($value)) {
            return $this->obfuscateNode($value, $state, is_string($key) ? $key : $parentKey);
        }

        if (is_string($value)) {
            return $this->obfuscateString((string) $key, $value, $state, $parentKey);
        }

        if (is_numeric($value)) {
            return $this->obfuscateNumeric((string) $key, (string) $value, $parentKey);
        }

        return $value;
    }

    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int} $state
     */
    private function obfuscateString(string $key, string $value, array &$state, ?string $parentKey): string
    {
        $normalizedKey = strtolower($key);
        $normalizedParent = $parentKey !== null ? strtolower($parentKey) : '';

        if ($this->shouldAliasName($normalizedKey, $normalizedParent, $value)) {
            return $this->aliasName($value, $state);
        }

        if ($this->shouldMaskIdentifier($normalizedKey, $normalizedParent, $value)) {
            return 'ID-XXX';
        }

        if ($this->looksLikeEmail($value)) {
            return $this->maskEmail($value);
        }

        if ($this->looksLikePhone($value)) {
            return $this->maskPhone($value);
        }

        return $value;
    }

    private function obfuscateNumeric(string $key, string $value, ?string $parentKey): string
    {
        $normalizedKey = strtolower($key);
        $normalizedParent = $parentKey !== null ? strtolower($parentKey) : '';

        if ($this->shouldMaskIdentifier($normalizedKey, $normalizedParent, $value)) {
            return 'ID-XXX';
        }

        return $value;
    }

    private function shouldAliasName(string $key, string $parent, string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($parent === 'learners' || $parent === 'students') {
            return true;
        }

        return str_contains($key, 'name') || $key === 'learner' || $key === 'student';
    }

    private function shouldMaskIdentifier(string $key, string $parent, string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($parent === 'learners' && ($key === 'id' || str_contains($key, 'id'))) {
            return true;
        }

        return str_contains($key, 'id') || str_contains($key, 'number');
    }

    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int} $state
     */
    private function aliasName(string $value, array &$state): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        if (array_key_exists($value, $state['reverse'])) {
            return $state['reverse'][$value];
        }

        $alias = $this->nextAlias($state);
        $state['aliases'][$alias] = $value;
        $state['reverse'][$value] = $alias;

        return $alias;
    }

    /**
     * @param array{aliases:array<string,string>, reverse:array<string,string>, nameCounter:int} $state
     */
    private function nextAlias(array &$state): string
    {
        $index = $state['nameCounter'];
        $state['nameCounter']++;

        $alphabetSize = 26;
        $digits = [];

        do {
            $letterIndex = $index % $alphabetSize;
            $digits[] = chr(ord('A') + $letterIndex);
            $index = intdiv($index, $alphabetSize) - 1;
        } while ($index >= 0);

        $suffix = implode('', array_reverse($digits));

        return 'Learner ' . $suffix;
    }

    private function looksLikeEmail(string $value): bool
    {
        return str_contains($value, '@');
    }

    private function looksLikePhone(string $value): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $value);
        return $digits !== null && strlen($digits) >= 7;
    }

    private function maskEmail(string $value): string
    {
        $parts = explode('@', $value, 2);
        if (count($parts) !== 2) {
            return 'hidden@example.com';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $localMasked = strlen($local) <= 2 ? str_repeat('*', strlen($local)) : substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 2, 1)) . substr($local, -1);

        return $localMasked . '@' . $domain;
    }

    private function maskPhone(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';
        if ($digits === '') {
            return 'XXX-XXX-XXXX';
        }

        $length = strlen($digits);
        $masked = str_repeat('X', max($length - 2, 0)) . substr($digits, -2);

        return $masked;
    }
}
