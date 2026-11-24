<?php

namespace App\Services\Payment\Dalipay;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Dalipay credentials from config/payments.php, checked before any call.
 *
 * Refused, with a reason that never contains a key:
 *  - a missing key or base URL, or a DALIPAY_ENV other than test/live;
 *  - a production key unless DALIPAY_ENV=live, and a test key with live;
 *  - a public and a secret key from different environments;
 *  - live over plain http;
 *  - test mode when APP_ENV=production (Dalipay's test mode approves
 *    payments that never happened, which would book real Bolt rides).
 */
final class DalipayConfig
{
    private const TEST_PUBLIC = 'gw_pk_test_';

    private const TEST_SECRET = 'gw_sk_test_';

    private function __construct(
        public readonly string $env,
        public readonly string $baseUrl,
        public readonly string $publicKey,
        #[SensitiveParameter] public readonly string $secretKey,
        /** Status checks, total seconds per request. */
        public readonly int $timeoutSeconds,
        /** Creating a collection, total seconds (15 to 60). */
        public readonly int $createTimeoutSeconds,
        /** @var array<string, string> Sokoni channel => the network name Dalipay records (display only) */
        public readonly array $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $config  config('payments.dalipay')
     *
     * @throws InvalidArgumentException when the configuration must not be used
     */
    public static function fromArray(#[SensitiveParameter] array $config, bool $productionApp): self
    {
        $env = strtolower(trim((string) ($config['env'] ?? '')));
        $public = trim((string) ($config['public_key'] ?? ''));
        $secret = trim((string) ($config['secret_key'] ?? ''));
        $base = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        $base = (string) preg_replace('#/api/v1$#', '', $base);

        if (! in_array($env, ['test', 'live'], true)) {
            throw new InvalidArgumentException('DALIPAY_ENV must be "test" or "live".');
        }
        if ($public === '' || $secret === '') {
            throw new InvalidArgumentException('DALIPAY_PUBLIC_KEY and DALIPAY_SECRET_KEY must both be set.');
        }
        if (! str_starts_with($public, 'gw_pk_') || ! str_starts_with($secret, 'gw_sk_')) {
            throw new InvalidArgumentException('The Dalipay keys are not a public key (gw_pk_…) and a secret key (gw_sk_…). Check they are not swapped.');
        }

        $testPublic = str_starts_with($public, self::TEST_PUBLIC);
        $testSecret = str_starts_with($secret, self::TEST_SECRET);
        if ($testPublic !== $testSecret) {
            throw new InvalidArgumentException('The Dalipay public and secret keys are from different environments (one test, one production).');
        }
        if (! $testPublic && $env !== 'live') {
            throw new InvalidArgumentException('Production Dalipay keys are refused unless DALIPAY_ENV=live.');
        }
        if ($testPublic && $env === 'live') {
            throw new InvalidArgumentException('Test Dalipay keys are refused with DALIPAY_ENV=live.');
        }
        if ($env === 'test' && $productionApp) {
            throw new InvalidArgumentException('Dalipay test mode is refused when APP_ENV=production: it approves payments that never happened.');
        }

        $scheme = parse_url($base, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true) || parse_url($base, PHP_URL_HOST) === null) {
            throw new InvalidArgumentException('DALIPAY_BASE_URL must be a full URL such as https://host (without /api/v1).');
        }
        if ($env === 'live' && $scheme !== 'https') {
            throw new InvalidArgumentException('DALIPAY_BASE_URL must use https with DALIPAY_ENV=live.');
        }

        // Display only (Dalipay takes the network from the number): an empty list is fine.
        $providers = array_filter((array) ($config['providers'] ?? []), fn ($code, $channel) => is_string($channel) && is_string($code) && $code !== '', ARRAY_FILTER_USE_BOTH);

        return new self(
            $env, $base, $public, $secret,
            max(5, (int) ($config['timeout_seconds'] ?? 15)),
            min(60, max(15, (int) ($config['create_timeout_seconds'] ?? 40))),
            $providers,
        );
    }

    public function isTest(): bool
    {
        return $this->env === 'test';
    }

    public function url(string $path): string
    {
        return $this->baseUrl.'/api/v1/'.ltrim($path, '/');
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return [
            'X-Public-Key' => $this->publicKey,
            'X-Secret-Key' => $this->secretKey,
            'Accept' => 'application/json',
        ];
    }

    /** Never shows the secret key in var_dump, dd() or a debug page. */
    public function __debugInfo(): array
    {
        return ['env' => $this->env, 'baseUrl' => $this->baseUrl, 'publicKey' => substr($this->publicKey, 0, 11).'…', 'secretKey' => '[redacted]'];
    }
}
