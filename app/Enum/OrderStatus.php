<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    // values
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // get allowed transitions
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PAID, self::CANCELLED],
            self::PAID => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    // validation can transition to
    public function canTransitionTo( OrderStatus $targetStatus): bool
    {
        return in_array($targetStatus, $this->allowedTransitions());
    }
}