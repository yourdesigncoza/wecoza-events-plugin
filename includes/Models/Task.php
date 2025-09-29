<?php
declare(strict_types=1);

namespace WeCozaEvents\Models;

final class Task
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';

    private string $id;
    private string $label;
    private string $status;
    private ?int $completedBy;
    private ?string $completedAt;
    private ?string $note;

    public function __construct(
        string $id,
        string $label,
        string $status = self::STATUS_OPEN,
        ?int $completedBy = null,
        ?string $completedAt = null,
        ?string $note = null
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->status = $status === self::STATUS_COMPLETED ? self::STATUS_COMPLETED : self::STATUS_OPEN;
        $this->completedBy = $completedBy;
        $this->completedAt = $completedAt;
        $this->note = $note;
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            (string) ($payload['id'] ?? ''),
            (string) ($payload['label'] ?? ''),
            (string) ($payload['status'] ?? self::STATUS_OPEN),
            isset($payload['completed_by']) ? (int) $payload['completed_by'] : null,
            isset($payload['completed_at']) ? (string) $payload['completed_at'] : null,
            isset($payload['note']) ? (string) $payload['note'] : null
        );
    }

    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
        ];

        if ($this->completedBy !== null) {
            $payload['completed_by'] = $this->completedBy;
        }

        if ($this->completedAt !== null) {
            $payload['completed_at'] = $this->completedAt;
        }

        if ($this->note !== null && $this->note !== '') {
            $payload['note'] = $this->note;
        }

        return $payload;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getCompletedBy(): ?int
    {
        return $this->completedBy;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function markCompleted(int $userId, string $timestamp, ?string $note = null): self
    {
        $clone = clone $this;
        $clone->status = self::STATUS_COMPLETED;
        $clone->completedBy = $userId;
        $clone->completedAt = $timestamp;
        $clone->note = $note;
        return $clone;
    }

    public function reopen(): self
    {
        $clone = clone $this;
        $clone->status = self::STATUS_OPEN;
        $clone->completedBy = null;
        $clone->completedAt = null;
        $clone->note = null;
        return $clone;
    }
}
