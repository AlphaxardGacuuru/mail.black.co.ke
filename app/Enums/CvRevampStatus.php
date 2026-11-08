<?php

namespace App\Enums;

/**
 * Where the user's revamped CV has got to.
 */
enum CvRevampStatus: string
{
    // The AI is rewriting the CV.
    case DRAFTING = 'drafting';
    // Ready for the user to review, section by section.
    case READY = 'ready';
    case FAILED = 'failed';
}
