<?php

namespace App\Enums;

enum JobApplicationStatus: string
{
    // Scored below the user's threshold; kept so it is never scored again.
    case NOT_MATCHED = 'not_matched';
    // A match with no apply email: the user applies on the employer's site.
    case APPLY_MANUALLY = 'apply_manually';
    case PENDING_APPROVAL = 'pending_approval';
    // Approved (or autopilot), waiting for today's sending allowance.
    case QUEUED = 'queued';
    case APPLIED = 'applied';
    case REPLIED = 'replied';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
