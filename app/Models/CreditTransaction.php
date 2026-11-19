<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CreditTransaction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'generation_id',
        'type',
        'amount_picodollars',
        'balance_after_picodollars',
        'reserved_after_picodollars',
        'description',
        'created_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount_picodollars' => 'integer',
            'balance_after_picodollars' => 'integer',
            'reserved_after_picodollars' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
