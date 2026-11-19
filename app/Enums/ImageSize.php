<?php

declare(strict_types=1);

namespace App\Enums;

enum ImageSize: string
{
    case Square = '1024x1024';
    case Landscape = '1536x1024';
    case Portrait = '1024x1536';

    public function label(): string
    {
        return match ($this) {
            self::Square => '1024 × 1024 (Square)',
            self::Landscape => '1536 × 1024 (Landscape)',
            self::Portrait => '1024 × 1536 (Portrait)',
        };
    }
}
