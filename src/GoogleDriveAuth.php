<?php

namespace Klytron\GoogleDriveFilesystem;

use Google\Client;

class GoogleDriveAuth
{
    public const FULL_ACCESS_SCOPE = 'https://www.googleapis.com/auth/drive';
    public const READONLY_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';
    public const APPDATA_SCOPE = 'https://www.googleapis.com/auth/drive.appdata';

    public const MAX_REFRESH_ATTEMPTS = 2;
    public const RETRY_BASE_DELAY_US = 100000;

    /**
     * Resolve the OAuth scopes to request.
     *
     * Accepts an array (from the config file) or a comma-separated string
     * (from the GOOGLE_DRIVE_SCOPES env var). Falls back to full drive
     * access when nothing usable is configured.
     */
    public static function resolveScopes(mixed $configured): array
    {
        if ($configured === null || $configured === '' || $configured === []) {
            return [self::FULL_ACCESS_SCOPE];
        }

        if (is_string($configured)) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $configured))));
            return $scopes === [] ? [self::FULL_ACCESS_SCOPE] : $scopes;
        }

        return array_values((array) $configured);
    }

    /**
     * Build a configured Google API client (no network calls).
     */
    public static function buildClient(array $config): Client
    {
        $client = new Client();
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->setRedirectUri($config['redirect_uri'] ?? 'http://localhost');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setScopes(self::resolveScopes($config['scopes'] ?? null));

        return $client;
    }

    /**
     * Whether an auth failure looks transient (worth one retry).
     *
     * Covers token-refresh races (401), rate limiting (429) and
     * upstream/network blips (408/5xx, timeouts, connection errors).
     */
    public static function isTransientAuthFailure(\Throwable $e): bool
    {
        $code = (int) $e->getCode();
        if (in_array($code, [401, 408, 425, 429, 500, 502, 503, 504], true)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(401|408|429|5\d\d)\b|unauthori[sz]ed|rate[\s_-]?limit|timeout|timed out|temporar|transient|connection|network|socket|curl|eof|broken pipe|service unavailable|bad gateway|gateway timeout/i',
            $e->getMessage()
        );
    }

    public static function actionableRefreshError(string $details): string
    {
        return 'Google Drive auth failed: refresh token rejected. '
            . 'Rotate GOOGLE_DRIVE_REFRESH_TOKEN (and check GOOGLE_DRIVE_CLIENT_ID/SECRET), '
            . 'then clear config/cache. Original error: ' . $details;
    }

    /**
     * Fetch an access token, retrying once on transient failures.
     *
     * @param callable|null $sleeper Receives microseconds to wait; defaults to usleep. Injectable for tests.
     */
    public static function refreshAccessTokenWithRetry(
        Client $client,
        string $refreshToken,
        int $maxAttempts = self::MAX_REFRESH_ATTEMPTS,
        ?callable $sleeper = null
    ): array {
        $maxAttempts = max(1, $maxAttempts);
        $sleeper = $sleeper ?? function (int $microseconds): void {
            usleep($microseconds);
        };
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < $maxAttempts && self::isTransientAuthFailure($e)) {
                    $sleeper(self::RETRY_BASE_DELAY_US * (2 ** ($attempt - 1)));
                    continue;
                }
                break;
            }

            if (is_array($token) && isset($token['error'])) {
                $errorDesc = $token['error_description'] ?? $token['error'];
                $e = new \RuntimeException('Token endpoint returned error: ' . $errorDesc);
                $lastError = $e;
                if ($attempt < $maxAttempts && self::isTransientAuthFailure($e)) {
                    $sleeper(self::RETRY_BASE_DELAY_US * (2 ** ($attempt - 1)));
                    continue;
                }
                break;
            }

            return (array) $token;
        }

        throw new \RuntimeException(
            self::actionableRefreshError($lastError ? $lastError->getMessage() : 'unknown error'),
            0,
            $lastError
        );
    }

    /**
     * Validate credentials without touching Drive files.
     *
     * With a refresh token this performs a token refresh (one retry on
     * transient failures); with only an access token it checks expiry
     * locally. No Drive API calls are made, so nothing is created,
     * modified or deleted.
     *
     * @return array The validated access token.
     */
    public static function assertCredentials(array $config, ?Client $client = null): array
    {
        if (empty($config['client_id'])) {
            throw new \InvalidArgumentException('Google Drive client_id is required');
        }

        if (empty($config['client_secret'])) {
            throw new \InvalidArgumentException('Google Drive client_secret is required');
        }

        $client = $client ?? self::buildClient($config);

        if (!empty($config['refresh_token'])) {
            $token = self::refreshAccessTokenWithRetry($client, $config['refresh_token']);
            $client->setAccessToken($token);

            return $token;
        }

        if (!empty($config['access_token'])) {
            $client->setAccessToken($config['access_token']);

            if ($client->isAccessTokenExpired()) {
                throw new \RuntimeException(
                    'Google Drive auth failed: access token is expired. '
                    . 'Set GOOGLE_DRIVE_REFRESH_TOKEN so it can be renewed, or provide a fresh GOOGLE_DRIVE_ACCESS_TOKEN.'
                );
            }

            return (array) $client->getAccessToken();
        }

        throw new \InvalidArgumentException('Either access_token or refresh_token is required for Google Drive authentication');
    }
}
