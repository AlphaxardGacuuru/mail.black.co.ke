<?php

namespace Tests\Feature;

use App\Http\Services\MailgunMailboxService;
use App\Models\MailgunDomain;
use GuzzleHttp\Psr7\Response;
use Mailgun\Api\Domain;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun;
use Mockery;
use Tests\TestCase;

/**
 * Mailboxes are provisioned as Mailgun SMTP credentials; the old mailboxes
 * endpoint now 404s.
 */
class MailgunMailboxServiceTest extends TestCase
{
    private function serviceExpecting(callable $expectation): MailgunMailboxService
    {
        $domains = Mockery::mock(Domain::class);
        $expectation($domains->shouldReceive('createCredential'));

        $client = Mockery::mock(Mailgun::class);
        $client->shouldReceive('domains')->andReturn($domains);
        $client->shouldNotReceive('mailboxes');

        $service = Mockery::mock(MailgunMailboxService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('client')->andReturn($client);

        return $service;
    }

    private function domain(): MailgunDomain
    {
        return new MailgunDomain([
            'domain' => 'money.black.co.ke',
            'endpoint' => 'api.mailgun.net',
            'api_key' => 'key',
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

    public function test_it_creates_an_smtp_credential_for_the_full_address(): void
    {
        $service = $this->serviceExpecting(fn ($expectation) => $expectation
            ->once()
            ->with('money.black.co.ke', 'al@money.black.co.ke', Mockery::on(fn ($password) => strlen($password) === 24)));

        $password = $service->createMailbox($this->domain(), 'Al@Money.black.co.ke');

        $this->assertSame(24, strlen($password));
    }

    public function test_an_existing_credential_counts_as_success(): void
    {
        $service = $this->serviceExpecting(fn ($expectation) => $expectation
            ->andThrow($this->mailgunError(409, 'Credentials already exist')));

        $this->assertSame(24, strlen($service->createMailbox($this->domain(), 'al@money.black.co.ke')));
    }

    public function test_other_mailgun_errors_are_rethrown(): void
    {
        $service = $this->serviceExpecting(fn ($expectation) => $expectation
            ->andThrow($this->mailgunError(401, 'Forbidden')));

        $this->expectException(HttpClientException::class);

        $service->createMailbox($this->domain(), 'al@money.black.co.ke');
    }
}
