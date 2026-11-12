<?php

namespace Tests\Feature;

use App\Agents\MailDraftAgent;
use App\Agents\MailRevampAgent;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\TestCase;

class ComposeAssistTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()
            ->create(['name' => 'Wanjiku Muhandi']);
    }

    public function test_it_drafts_an_email_from_an_instruction(): void
    {
        MailDraftAgent::fake(["Hi Jane,\n\nCould you send the Q3 report by Friday?\n\nThanks,\nWanjiku"]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/messages/draft', [
                'instruction' => 'ask Jane for the Q3 report by Friday',
                'subject' => 'Q3 report',
                'to' => ['jane@example.com'],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Draft Ready')
            ->assertJsonPath('data.html', '<p>Hi Jane,</p><p>Could you send the Q3 report by Friday?</p><p>Thanks,<br>Wanjiku</p>');

        MailDraftAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Wanjiku Muhandi')
            && str_contains($prompt->prompt, 'To: jane@example.com')
            && str_contains($prompt->prompt, 'Subject: Q3 report')
            && str_contains($prompt->prompt, 'ask Jane for the Q3 report by Friday'));

        $this->assertSame(1, AiUsage::where('feature', AiUsage::FEATURE_MAIL_DRAFT)->where('user_id', $this->owner->id)->count());
    }

    public function test_a_draft_needs_an_instruction(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/messages/draft', ['instruction' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('instruction');
    }

    public function test_it_rewrites_the_users_message_in_the_chosen_style(): void
    {
        MailRevampAgent::fake(["Dear Kevin,\n\nThank you for your message.\n\nKind regards,\nWanjiku"]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/messages/revamp', [
                'bodyHtml' => '<p>hey kevin</p><p>thx for ur msg &amp; the <strong>photos</strong></p><p>wanjiku</p>',
                'style' => 'formal',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Rewrite Ready')
            ->assertJsonPath('data.text', "Dear Kevin,\n\nThank you for your message.\n\nKind regards,\nWanjiku");

        MailRevampAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'more formal')
            && str_contains($prompt->prompt, "hey kevin\nthx for ur msg & the photos\nwanjiku")
            && ! str_contains($prompt->prompt, '<p>'));

        $this->assertSame(1, AiUsage::where('feature', AiUsage::FEATURE_MAIL_REVAMP)->count());
    }

    public function test_only_known_rewrite_styles_are_accepted(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/messages/revamp', ['bodyHtml' => '<p>Hello there</p>', 'style' => 'pirate'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('style');
    }

    public function test_an_ai_failure_is_reported_without_an_error_page(): void
    {
        MailRevampAgent::fake(fn () => throw new RuntimeException('Gemini is down'));

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/messages/revamp', ['bodyHtml' => '<p>Hello there, how are you?</p>', 'style' => 'polish'])
            ->assertStatus(503)
            ->assertJsonPath('message', "Couldn't rewrite your message right now. Please try again later.");
    }

    public function test_guests_cannot_use_the_ai(): void
    {
        $this->postJson('/api/messages/draft', ['instruction' => 'hello'])
            ->assertUnauthorized();
    }
}
