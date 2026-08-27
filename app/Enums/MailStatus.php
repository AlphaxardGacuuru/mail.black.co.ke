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
    case TEMPORARY_FAILED = 'temporary_failed';
    case PERMANENT_FAILED = 'permanent_failed';
    case COMPLAINED = 'complained';
    case UNSUBSCRIBED = 'unsubscribed';
    case RECEIVED = 'received';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
