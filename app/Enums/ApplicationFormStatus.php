<?php

namespace App\Enums;

/**
 * Where an application on the employer's own form has got to.
 */
enum ApplicationFormStatus: string
{
    // The form is being opened and its questions read.
    case PREPARING = 'preparing';
    // Questions and suggested answers are ready for the user to review.
    case READY = 'ready';
    // The form can't be filled in here (sign-in, bot wall, no form found).
    case BLOCKED = 'blocked';
    case FAILED = 'failed';
    // Approved by the user and being filled in and submitted.
    case SUBMITTING = 'submitting';
    case SUBMITTED = 'submitted';
    // Submitting didn't go through; the answers are kept to try again.
    case SUBMIT_FAILED = 'submit_failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
