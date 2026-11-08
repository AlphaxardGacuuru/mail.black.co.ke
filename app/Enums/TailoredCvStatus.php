<?php

namespace App\Enums;

/**
 * Where the CV tailored to one job application has got to.
 */
enum TailoredCvStatus: string
{
    case TAILORING = 'tailoring';
    case READY = 'ready';
    // Couldn't be tailored; the user's revamped CV is sent instead.
    case FAILED = 'failed';
}
