<?php

namespace Klytron\GoogleDriveFilesystem\Tests;

use Google\Client;
use Klytron\GoogleDriveFilesystem\GoogleDriveAuth;
use Orchestra\Testbench\TestCase;

class FakeGoogleClient extends Client
{
    /** @var array<int, array{throw?: \Throwable, return?: array}> */
    public array $queued = [];
    public int $fetchCalls = 0;

    public function fetchAccessTokenWithRefreshToken($refreshToken = null)
    {
        $this->fetchCalls++;
        $next = array_shift($this->queued) ?? [];

        if (isset($next['throw'])) {
            throw $next['throw'];
        }

        return $next['return'] ?? ['access_token' => 'fake-access-token', 'expires_in' => 3600];
    }
}

class GoogleDriveAuthTest extends TestCase
{
    private function fakeClient(array $queued = []): FakeGoogleClient
    {
        $client = new FakeGoogleClient();
        $client->queued = $queued;
        return $client;
    }

    private function noSleep(): callable
    {
        return function (int $microseconds): void {
            $this->sleeps[] = $microseconds;
        };
    }

    /** @var array<int, int> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sleeps = [];
    }

    public function test_resolve_scopes_defaults_to_full_access()
    {
        $this->assertSame([GoogleDriveAuth::FULL_ACCESS_SCOPE], GoogleDriveAuth::resolveScopes(null));
        $this->assertSame([GoogleDriveAuth::FULL_ACCESS_SCOPE], GoogleDriveAuth::resolveScopes(''));
        $this->assertSame([GoogleDriveAuth::FULL_ACCESS_SCOPE], GoogleDriveAuth::resolveScopes([]));
        $this->assertSame([GoogleDriveAuth::FULL_ACCESS_SCOPE], GoogleDriveAuth::resolveScopes('  ,  '));
    }

    public function test_resolve_scopes_parses_comma_separated_env_string()
    {
        $resolved = GoogleDriveAuth::resolveScopes(
            GoogleDriveAuth::READONLY_SCOPE . ' , ' . GoogleDriveAuth::APPDATA_SCOPE
        );

        $this->assertSame([GoogleDriveAuth::READONLY_SCOPE, GoogleDriveAuth::APPDATA_SCOPE], $resolved);
    }

    public function test_resolve_scopes_passes_through_array()
    {
        $this->assertSame(
            [GoogleDriveAuth::READONLY_SCOPE],
            GoogleDriveAuth::resolveScopes([GoogleDriveAuth::READONLY_SCOPE])
        );
    }

    public function test_build_client_applies_configured_scopes()
    {
        $client = GoogleDriveAuth::buildClient([
            'client_id' => 'id',
            'client_secret' => 'secret',
            'scopes' => GoogleDriveAuth::READONLY_SCOPE,
        ]);

        $this->assertSame([GoogleDriveAuth::READONLY_SCOPE], (array) $client->getScopes());
    }

    public function test_build_client_defaults_to_full_access_scope()
    {
        $client = GoogleDriveAuth::buildClient(['client_id' => 'id', 'client_secret' => 'secret']);

        $this->assertSame([GoogleDriveAuth::FULL_ACCESS_SCOPE], (array) $client->getScopes());
    }

    public function test_refresh_retries_once_on_transient_failure_then_succeeds()
    {
        $client = $this->fakeClient([
            ['throw' => new \RuntimeException('OAuth2: 503 Service Unavailable', 503)],
            ['return' => ['access_token' => 'fresh-token', 'expires_in' => 3600]],
        ]);

        $token = GoogleDriveAuth::refreshAccessTokenWithRetry($client, 'refresh-token', 2, $this->noSleep());

        $this->assertSame('fresh-token', $token['access_token']);
        $this->assertSame(2, $client->fetchCalls);
        $this->assertSame([GoogleDriveAuth::RETRY_BASE_DELAY_US], $this->sleeps);
    }

    public function test_retry_uses_exponential_backoff()
    {
        $client = $this->fakeClient([
            ['throw' => new \RuntimeException('connection reset by peer')],
            ['throw' => new \RuntimeException('connection reset by peer')],
            ['throw' => new \RuntimeException('connection reset by peer')],
        ]);

        try {
            GoogleDriveAuth::refreshAccessTokenWithRetry($client, 'refresh-token', 3, $this->noSleep());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GOOGLE_DRIVE_REFRESH_TOKEN', $e->getMessage());
        }

        $this->assertSame(3, $client->fetchCalls);
        $this->assertSame(
            [GoogleDriveAuth::RETRY_BASE_DELAY_US, GoogleDriveAuth::RETRY_BASE_DELAY_US * 2],
            $this->sleeps
        );
    }

    public function test_refresh_does_not_retry_permanent_failures()
    {
        $client = $this->fakeClient([
            ['throw' => new \RuntimeException('Token endpoint returned error: invalid_grant', 400)],
        ]);

        try {
            GoogleDriveAuth::refreshAccessTokenWithRetry($client, 'refresh-token', 2, $this->noSleep());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GOOGLE_DRIVE_REFRESH_TOKEN', $e->getMessage());
            $this->assertStringContainsString('invalid_grant', $e->getMessage());
        }

        $this->assertSame(1, $client->fetchCalls);
        $this->assertSame([], $this->sleeps);
    }

    public function test_error_array_response_throws_actionable_error()
    {
        $client = $this->fakeClient([
            ['return' => ['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.']],
        ]);

        try {
            GoogleDriveAuth::refreshAccessTokenWithRetry($client, 'refresh-token', 2, $this->noSleep());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GOOGLE_DRIVE_REFRESH_TOKEN', $e->getMessage());
            $this->assertStringContainsString('GOOGLE_DRIVE_CLIENT_ID', $e->getMessage());
        }

        $this->assertSame(1, $client->fetchCalls);
    }

    public function test_assert_credentials_requires_client_id_and_secret()
    {
        $this->expectException(\InvalidArgumentException::class);
        GoogleDriveAuth::assertCredentials(['client_secret' => 'secret', 'refresh_token' => 'token'], $this->fakeClient());
    }

    public function test_assert_credentials_requires_a_token()
    {
        $this->expectException(\InvalidArgumentException::class);
        GoogleDriveAuth::assertCredentials(['client_id' => 'id', 'client_secret' => 'secret'], $this->fakeClient());
    }

    public function test_assert_credentials_validates_refresh_token()
    {
        $client = $this->fakeClient([['return' => ['access_token' => 'fresh-token', 'expires_in' => 3600]]]);

        $token = GoogleDriveAuth::assertCredentials(
            ['client_id' => 'id', 'client_secret' => 'secret', 'refresh_token' => 'refresh-token'],
            $client
        );

        $this->assertSame('fresh-token', $token['access_token']);
        $this->assertSame(1, $client->fetchCalls);
    }

    public function test_assert_credentials_rejects_invalid_refresh_token()
    {
        $client = $this->fakeClient([
            ['return' => ['error' => 'invalid_grant', 'error_description' => 'Bad Request']],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/GOOGLE_DRIVE_REFRESH_TOKEN/');

        GoogleDriveAuth::assertCredentials(
            ['client_id' => 'id', 'client_secret' => 'secret', 'refresh_token' => 'stale-token'],
            $client
        );
    }

    public function test_assert_credentials_accepts_unexpired_access_token()
    {
        $token = GoogleDriveAuth::assertCredentials([
            'client_id' => 'id',
            'client_secret' => 'secret',
            'access_token' => ['access_token' => 'plain-token', 'expires_in' => 3600, 'created' => time()],
        ]);

        $this->assertSame('plain-token', $token['access_token']);
    }

    public function test_assert_credentials_rejects_expired_access_token()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/expired/');

        GoogleDriveAuth::assertCredentials([
            'client_id' => 'id',
            'client_secret' => 'secret',
            'access_token' => ['access_token' => 'stale-token', 'expires_in' => 1, 'created' => time() - 7200],
        ]);
    }
}
