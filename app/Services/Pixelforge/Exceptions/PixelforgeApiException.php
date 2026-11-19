<?php

declare(strict_types=1);

namespace App\Services\Pixelforge\Exceptions;

use RuntimeException;

final class PixelforgeApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $validationErrors
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $errorType,
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly ?string $param = null,
        public readonly array $validationErrors = [],
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($errorMessage !== '' ? $errorMessage : "Pixelforge API error ({$statusCode}: {$errorCode})");
    }

    public function isConcurrencyOrRateLimit(): bool
    {
        return in_array($this->errorCode, ['concurrency_limit_exceeded', 'rate_limit_exceeded'], true);
    }

    public function isOverloaded(): bool
    {
        return $this->statusCode === 503 || $this->errorCode === 'overloaded';
    }

    public function isRetryableTransient(): bool
    {
        return $this->isOverloaded() || $this->isConcurrencyOrRateLimit();
    }
}
