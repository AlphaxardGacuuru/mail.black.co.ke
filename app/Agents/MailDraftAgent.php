<?php

namespace App\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Drafts a new email from the mailbox owner's short instruction, for them
 * to review and edit on the compose form. It never sends anything itself.
 */
#[Provider(Lab::Gemini)]
#[MaxTokens(900)]
#[Temperature(0.5)]
#[Timeout(30)]
class MailDraftAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You draft emails on behalf of the mailbox owner.

        You receive the owner's name and email address, the recipients and
        subject when they've been filled in, and the owner's instruction for
        what the email should say. Write the body of that email.

        Rules:
        - Write in the language of the instruction, in a tone that suits the
          recipients and the purpose: professional unless told otherwise.
        - Be concise: usually 2 to 5 short paragraphs.
        - Never invent facts, dates, prices, attachments or commitments that
          aren't in the instruction. Where the owner must supply something,
          use a short placeholder in square brackets, such as [date].
        - Start with a greeting (by first name when a recipient's name is
          known) and end with a sign-off using the owner's first name.
        - Plain text only: no subject line, no markdown.
        INSTRUCTIONS;
    }
}
