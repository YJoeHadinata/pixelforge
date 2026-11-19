<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

enum GenerationStatus: string
{
    case Submitting = 'submitting';
    case QueuedLocally = 'queued_locally';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::Submitting, self::QueuedLocally => 1,
            self::Queued => 2,
            self::Processing => 3,
            self::Succeeded, self::Failed => 4,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    public function isInFlight(): bool
    {
        return ! $this->isTerminal();
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if ($this === self::Submitting && $next === self::QueuedLocally) {
            return true;
        }

        return $next->rank() > $this->rank();
    }

    public static function fromPixelforge(string $status): self
    {
        return match ($status) {
            'queued' => self::Queued,
            'processing' => self::Processing,
            'succeeded' => self::Succeeded,
            'failed' => self::Failed,
            default => throw new InvalidArgumentException("Unsupported Pixelforge generation status: {$status}"),
        };
    }
}
