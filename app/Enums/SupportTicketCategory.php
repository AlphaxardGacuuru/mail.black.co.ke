<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case MAINTENANCE = 'maintenance';
    case BILLING = 'billing';
    case SECURITY = 'security';
    case COMPLAINT = 'complaint';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
