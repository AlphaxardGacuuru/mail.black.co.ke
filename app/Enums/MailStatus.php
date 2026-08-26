<?php

namespace App\Enums;

enum MailStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case FAILED = 'failed';
    case BOUNCED = 'bounced';
    case RECEIVED = 'received';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
