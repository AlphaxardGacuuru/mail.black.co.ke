<?php

namespace Tests\Feature;

use App\Agents\ReplySuggestionAgent;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\TestCase;

class ReplySuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private MailThread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()
            ->create(['name' => 'Wanjiku Muhandi']);
        $this->thread = MailThread::create(['user_id' => $this->owner->id, 'subject' => 'Viewing on Saturday']);
    }

    private function message(MailThread $thread, string $body, string $direction = 'inbound', int $minutesAgo = 0): MailMessage
    {
        return MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $thread->user_id,
            'direction' => $direction,
            'folder' => $direction === 'inbound' ? 'inbox' : 'sent',
            'from_address' => $direction === 'inbound'
                ? ['name' => 'Kevin Otieno', 'address' => 'kevin@example.com']
                : ['name' => 'Wanjiku Muhandi', 'address' => 'wanjiku@example.com'],
            'to' => [['name' => 'Wanjiku Muhandi', 'address' => 'wanjiku@example.com']],
            'subject' => $thread->subject,
            'body_text' => $body,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_it_suggests_a_reply_from_the_thread(): void
    {
        ReplySuggestionAgent::fake(["Hi Kevin,\n\nSaturday at [time] works.\n\nThanks,\nWanjiku"]);

        $this->message($this->thread, 'Is the flat still available?', minutesAgo: 30);
        $last = $this->message($this->thread, "Can I view it on Saturday?\n\nOn Mon, Kevin wrote:\n> Is the flat still available?");

        $other = MailThread::create(['user_id' => $this->owner->id, 'subject' => 'Unrelated']);
        $this->message($other, 'SECRET from another thread', minutesAgo: 5);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$last->id}/suggest-reply")
            ->assertOk()
            ->assertJsonPath('data.text', "Hi Kevin,\n\nSaturday at [time] works.\n\nThanks,\nWanjiku")
            ->assertJsonPath('data.html', '<p>Hi Kevin,</p><p>Saturday at [time] works.</p><p>Thanks,<br>Wanjiku</p>')
            ->assertJsonPath('cached', false);

        ReplySuggestionAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains($prompt->prompt, 'Wanjiku Muhandi')
                && str_contains($prompt->prompt, 'Can I view it on Saturday?')
                && str_contains($prompt->prompt, 'Is the flat still available?')
                && ! str_contains($prompt->prompt, '> Is the flat')
                && ! str_contains($prompt->prompt, 'wrote:')
                && ! str_contains($prompt->prompt, 'SECRET');
        });
    }

    public function test_a_second_request_reuses_the_suggestion(): void
    {
        ReplySuggestionAgent::fake(['First draft', 'Second draft']);
        $message = $this->message($this->thread, 'Hello?');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk()
            ->assertJsonPath('data.text', 'First draft')
            ->assertJsonPath('cached', true);
    }

    public function test_the_ai_is_asked_only_once_per_message(): void
    {
        ReplySuggestionAgent::fake(['First draft', 'Second draft']);
        $message = $this->message($this->thread, 'Hello?');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply", ['mode' => 'reply'])
            ->assertOk();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply", ['mode' => 'reply-all'])
            ->assertOk()
            ->assertJsonPath('data.text', 'First draft')
            ->assertJsonPath('cached', true);

        $this->travel(2)
            ->days();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk()
            ->assertJsonPath('data.text', 'First draft');
    }

    public function test_each_message_gets_its_own_suggestion(): void
    {
        ReplySuggestionAgent::fake(['One', 'Two']);
        $first = $this->message($this->thread, 'First?', minutesAgo: 10);
        $second = $this->message($this->thread, 'Second?');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$first->id}/suggest-reply")
            ->assertJsonPath('data.text', 'One');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$second->id}/suggest-reply")
            ->assertOk()
            ->assertJsonPath('data.text', 'Two');
    }

    public function test_a_failing_ai_provider_gives_a_friendly_error(): void
    {
        ReplySuggestionAgent::fake(fn () => throw new RuntimeException('Gemini is down'));
        $message = $this->message($this->thread, 'Hello?');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertStatus(503)
            ->assertJsonPath('data', null);
    }

    public function test_suggestions_cannot_inject_html(): void
    {
        ReplySuggestionAgent::fake(['<script>alert(1)</script> Thanks']);
        $message = $this->message($this->thread, 'Hello?');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk()
            ->assertJsonPath('data.html', '<p>&lt;script&gt;alert(1)&lt;/script&gt; Thanks</p>');
    }

    public function test_other_users_messages_are_not_found(): void
    {
        ReplySuggestionAgent::fake(['Nope'])
            ->preventStrayPrompts();
        $message = $this->message($this->thread, 'Private');

        $this->actingAs(User::factory()
            ->create(), 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertNotFound();
    }

    public function test_forwards_are_refused(): void
    {
        $message = $this->message($this->thread, 'Hello?');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply", ['mode' => 'forward'])
            ->assertUnprocessable();
    }

    public function test_guests_are_refused(): void
    {
        $message = $this->message($this->thread, 'Hello?');

        $this->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertUnauthorized();
    }
}
