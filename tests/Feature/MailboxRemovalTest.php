<?php

namespace Tests\Feature;

use App\Http\Services\MailgunMailboxService;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mailgun\Api\Domain;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Removing a mailbox, by its owner or an admin, deletes it on Mailgun too.
 */
class MailboxRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MailgunDomain $domain;

    private MockInterface $mailgun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->domain = MailgunDomain::create(['user_id' => $this->user->id, 'domain' => 'money.black.co.ke', 'api_key' => 'key']);

        $this->mailgun = Mockery::mock(Domain::class);

        $client = Mockery::mock(Mailgun::class);
        $client->shouldReceive('domains')->andReturn($this->mailgun);

        $service = Mockery::mock(MailgunMailboxService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('client')->andReturn($client);

        $this->app->instance(MailgunMailboxService::class, $service);
    }

    private function mailbox(string $address, ?User $user = null, ?MailgunDomain $domain = null): MailgunAccount
    {
        return MailgunAccount::create([
            'user_id' => ($user ?? $this->user)->id,
            'mailgun_domain_id' => ($domain ?? $this->domain)->id,
            'mailbox_address' => $address,
        ]);
    }

    private function mailgunError(int $code, string $message): HttpClientException
    {
        return new HttpClientException($message, $code, new Response(
            $code,
            ['Content-Type' => 'application/json'],
            json_encode(['message' => $message])
        ));
    }

    public function test_removing_a_mailbox_deletes_it_on_mailgun_and_moves_the_active_one(): void
    {
        $removed = $this->mailbox('Al@money.black.co.ke');
        $kept = $this->mailbox('hello@money.black.co.ke');
        $this->user->update(['active_mailgun_account_id' => $removed->id]);

        $this->mailgun->shouldReceive('deleteCredential')
            ->once()
            ->with('money.black.co.ke', 'al@money.black.co.ke');

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/settings/mailgun-accounts/{$removed->id}")
            ->assertOk()
            ->assertJson(['message' => 'Mailbox Removed'])
            ->assertJsonCount(1, 'accounts')
            ->assertJsonPath('accounts.0.id', $kept->id)
            ->assertJsonPath('accounts.0.isActive', true);

        $this->assertModelMissing($removed);
    }

    public function test_a_mailbox_already_gone_on_mailgun_is_still_removed(): void
    {
        $account = $this->mailbox('al@money.black.co.ke');

        $this->mailgun->shouldReceive('deleteCredential')
            ->andThrow($this->mailgunError(404, 'Credentials not found'));

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/settings/mailgun-accounts/{$account->id}")
            ->assertOk();

        $this->assertModelMissing($account);
    }

    public function test_a_mailgun_failure_keeps_the_mailbox(): void
    {
        $account = $this->mailbox('al@money.black.co.ke');

        $this->mailgun->shouldReceive('deleteCredential')
            ->andThrow($this->mailgunError(401, 'Forbidden'));

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/settings/mailgun-accounts/{$account->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $this->assertModelExists($account);
    }

    public function test_a_job_search_address_cant_be_removed(): void
    {
        $account = $this->mailbox('al@mail.black.co.ke', domain: MailgunDomain::platform());

        $this->mailgun->shouldNotReceive('deleteCredential');

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/settings/mailgun-accounts/{$account->id}")
            ->assertUnprocessable();

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->deleteJson("/api/admin/mailgun-accounts/{$account->id}")
            ->assertUnprocessable();

        $this->assertModelExists($account);
    }

    public function test_someone_elses_mailbox_cant_be_removed(): void
    {
        $account = $this->mailbox('al@money.black.co.ke');

        $this->mailgun->shouldNotReceive('deleteCredential');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/settings/mailgun-accounts/{$account->id}")
            ->assertNotFound();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/mailgun-accounts/{$account->id}")
            ->assertForbidden();

        $this->assertModelExists($account);
    }

    public function test_removing_a_domain_deletes_its_mailboxes_on_mailgun(): void
    {
        $this->mailbox('al@money.black.co.ke');
        $this->mailbox('hello@money.black.co.ke');

        $this->mailgun->shouldReceive('deleteCredential')
            ->twice();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/settings/mailgun-domains/{$this->domain->id}")
            ->assertOk()
            ->assertJson(['message' => 'Mailgun Domain Removed', 'domains' => [], 'accounts' => []]);

        $this->assertSame(0, MailgunAccount::count());
    }

    public function test_an_admin_can_remove_anyones_mailbox(): void
    {
        $account = $this->mailbox('al@money.black.co.ke');

        $this->mailgun->shouldReceive('deleteCredential')
            ->once()
            ->with('money.black.co.ke', 'al@money.black.co.ke');

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->deleteJson("/api/admin/mailgun-accounts/{$account->id}")
            ->assertOk()
            ->assertJson(['message' => 'Mailbox Removed']);

        $this->assertModelMissing($account);
    }

    public function test_deleting_a_user_deletes_their_mailboxes_on_mailgun_but_not_their_job_search_address(): void
    {
        $this->mailbox('al@money.black.co.ke');
        $this->mailbox('al@mail.black.co.ke', domain: MailgunDomain::platform());

        $this->mailgun->shouldReceive('deleteCredential')
            ->once()
            ->with('money.black.co.ke', 'al@money.black.co.ke');

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->deleteJson("/api/admin/users/{$this->user->id}")
            ->assertOk();

        $this->assertModelMissing($this->user);
    }
}
