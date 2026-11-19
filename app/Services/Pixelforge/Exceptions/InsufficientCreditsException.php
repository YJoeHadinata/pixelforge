<?php

declare(strict_types=1);

namespace App\Services\Pixelforge\Exceptions;

use RuntimeException;

final class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $availablePicodollars,
        public readonly int $requiredPicodollars,
        string $message = 'Insufficient credit balance to cover the maximum estimated generation cost.',
    ) {
        parent::__construct($message);
    }
}
