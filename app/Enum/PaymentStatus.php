<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    // values
    public static function values(): array
    {   
        return array_column(self::cases(), 'value');
    }
}
