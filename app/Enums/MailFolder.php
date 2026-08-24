<?php

namespace App\Enums;

enum MailFolder: string
{
    case INBOX = 'inbox';
    case SENT = 'sent';
    case DRAFT = 'draft';
    case TRASH = 'trash';
    case ARCHIVE = 'archive';
    case SPAM = 'spam';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
