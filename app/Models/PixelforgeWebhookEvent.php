<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PixelforgeWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'pixelforge_generation_id',
        'delivery_attempt',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_attempt' => 'integer',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
