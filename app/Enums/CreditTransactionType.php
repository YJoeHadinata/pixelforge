<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditTransactionType: string
{
    case TopUp = 'top_up';
    case Reserve = 'reserve';
    case Release = 'release';
    case Charge = 'charge';
    case Adjustment = 'adjustment';
}
