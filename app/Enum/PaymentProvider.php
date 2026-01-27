<?php

namespace App\Enum;

enum PaymentProvider: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    
    
    // values: 'stripe', 'paypal'
    public static function Values(): array
    {
        return array_column(self::cases(), 'value');
    }
}