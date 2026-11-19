<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'pixelforge_id',
        'idempotency_key',
        'status',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'rates_snapshot',
        'usage',
        'estimated_min_provider_cost_picodollars',
        'estimated_expected_provider_cost_picodollars',
        'estimated_max_provider_cost_picodollars',
        'estimated_min_user_cost_picodollars',
        'estimated_expected_user_cost_picodollars',
        'estimated_max_user_cost_picodollars',
        'reserved_user_cost_picodollars',
        'actual_provider_cost_picodollars',
        'actual_user_cost_picodollars',
        'profit_picodollars',
        'settled_at',
        'image_url',
        'image_url_expires_at',
        'error_code',
        'error_message',
        'retry_count',
        'pixelforge_created_at',
        'pixelforge_started_at',
        'pixelforge_completed_at',
        'last_synced_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function isImageExpired(): bool
    {
        if ($this->image_url_expires_at === null) {
            return false;
        }

        return $this->image_url_expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'rates_snapshot' => 'array',
            'usage' => 'array',
            'estimated_min_provider_cost_picodollars' => 'integer',
            'estimated_expected_provider_cost_picodollars' => 'integer',
            'estimated_max_provider_cost_picodollars' => 'integer',
            'estimated_min_user_cost_picodollars' => 'integer',
            'estimated_expected_user_cost_picodollars' => 'integer',
            'estimated_max_user_cost_picodollars' => 'integer',
            'reserved_user_cost_picodollars' => 'integer',
            'actual_provider_cost_picodollars' => 'integer',
            'actual_user_cost_picodollars' => 'integer',
            'profit_picodollars' => 'integer',
            'retry_count' => 'integer',
            'settled_at' => 'immutable_datetime',
            'image_url_expires_at' => 'immutable_datetime',
            'pixelforge_created_at' => 'immutable_datetime',
            'pixelforge_started_at' => 'immutable_datetime',
            'pixelforge_completed_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
        ];
    }
}
