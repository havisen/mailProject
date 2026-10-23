<?php

declare(strict_types=1);

use App\Mail\SmtpConnectionTester;
use App\Mail\SmtpProbe;
use App\Repositories\AccountRepository;
use App\Security\CredentialCipher;
use App\Security\EndpointPolicy;
use App\Services\AccountService;

require __DIR__ . '/../../src/Security/CredentialCipher.php';
require __DIR__ . '/../../src/Security/EndpointPolicy.php';
require __DIR__ . '/../../src/Repositories/AccountRepository.php';
require __DIR__ . '/../../src/Services/SendingAccountProvider.php';
require __DIR__ . '/../../src/Services/AccountService.php';
require __DIR__ . '/../../src/Mail/SmtpConnectionTester.php';

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;
    $tests[$name] = $callback;
}

function expect(bool $condition, string $message = 'Expectation failed.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message === '' ? '' : $message . ' ')
            . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
        );
    }
}

function throws(string $className, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $className) {
            return;
        }

        throw new RuntimeException('Expected ' . $className . ', got ' . $throwable::class . '.');
    }

    throw new RuntimeException('Expected ' . $className . ', but nothing was thrown.');
}

final class MemoryAccountRepository extends AccountRepository
{
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    public function __construct()
    {
    }

    public function listForPanel(int $limit = 100): array
    {
        return array_map(fn (array $row): array => $this->withoutSecret($row), array_values($this->rows));
    }

    public function findByPublicId(string $publicId): ?array
    {
        return isset($this->rows[$publicId]) ? $this->withoutSecret($this->rows[$publicId]) : null;
    }

    public function findWithSecretByPublicId(string $publicId): ?array
    {
        return $this->rows[$publicId] ?? null;
    }

    public function insert(array $account): int
    {
        $this->rows[$account['public_id']] = $account;
        return count($this->rows);
    }

    public function update(string $publicId, array $account): bool
    {
        if (!isset($this->rows[$publicId])) {
            return false;
        }

        $this->rows[$publicId] = array_replace($this->rows[$publicId], $account);
        return true;
    }

    public function disable(string $publicId, string $reason): bool
    {
        if (!isset($this->rows[$publicId])) {
            return false;
        }

        $this->rows[$publicId]['enabled'] = false;
        $this->rows[$publicId]['pause_reason'] = $reason;
        return true;
    }

    public function delete(string $publicId): bool
    {
        if (!isset($this->rows[$publicId])) {
            return false;
        }

        unset($this->rows[$publicId]);
        return true;
    }

    public function existsPublicId(string $publicId): bool
    {
        return isset($this->rows[$publicId]);
    }

    /** @param array<string, mixed> $row */
    private function withoutSecret(array $row): array
    {
        unset($row['password_enc'], $row['password_aad']);
        return $row;
    }
}

final class FakeSmtpProbe implements SmtpProbe
{
    public ?string $result = null;
    public int $calls = 0;
    public int $connectTimeout = 0;
    public int $operationTimeout = 0;

    public function probe(array $configuration, int $connectTimeoutSeconds, int $operationTimeoutSeconds): ?string
    {
        $this->calls++;
        $this->connectTimeout = $connectTimeoutSeconds;
        $this->operationTimeout = $operationTimeoutSeconds;
        return $this->result;
    }
}

$keyOne = base64_encode(random_bytes(CredentialCipher::KEY_BYTES));
$keyTwo = base64_encode(random_bytes(CredentialCipher::KEY_BYTES));
$cipher = new CredentialCipher(['k1' => $keyOne, 'k2' => $keyTwo], 'k1');
$accountId = 'acct_' . str_repeat('a', 32);
$context = CredentialCipher::contextFor($accountId, 'password');

test('cipher round-trips a context-bound value', function () use ($cipher, $context): void {
    same('smtp-secret', $cipher->decrypt($cipher->encrypt('smtp-secret', $context), $context));
});
test('cipher uses a fresh nonce', function () use ($cipher, $context): void {
    expect($cipher->encrypt('same', $context) !== $cipher->encrypt('same', $context));
});
test('cipher rejects an empty credential', fn () => throws(InvalidArgumentException::class, fn () => $cipher->encrypt('')));
test('cipher detects ciphertext tampering', function () use ($cipher, $context): void {
    $stored = $cipher->encrypt('secret', $context);
    $stored[strlen($stored) - 1] = $stored[strlen($stored) - 1] === 'A' ? 'B' : 'A';
    throws(RuntimeException::class, fn () => $cipher->decrypt($stored, $context));
});
test('cipher rejects the wrong context', function () use ($cipher, $context): void {
    $stored = $cipher->encrypt('secret', $context);
    throws(RuntimeException::class, fn () => $cipher->decrypt($stored, 'acct:other:password'));
});
test('cipher rejects the wrong key', function () use ($cipher, $context): void {
    $stored = $cipher->encrypt('secret', $context);
    $other = new CredentialCipher(['k1' => base64_encode(random_bytes(32))], 'k1');
    throws(RuntimeException::class, fn () => $other->decrypt($stored, $context));
});
test('cipher rejects an unknown version', function () use ($cipher, $context): void {
    $stored = $cipher->encrypt('secret', $context);
    throws(RuntimeException::class, fn () => $cipher->decrypt('v2' . substr($stored, 2), $context));
});
test('cipher rejects a malformed value', fn () => throws(RuntimeException::class, fn () => $cipher->decrypt('bad')));
test('credential context validates account IDs', fn () => throws(InvalidArgumentException::class, fn () => CredentialCipher::contextFor('bad', 'password')));

test('port 465 selects implicit TLS', fn () => same('implicit', EndpointPolicy::tlsForPort(465)));
test('port 587 selects STARTTLS', fn () => same('starttls', EndpointPolicy::tlsForPort(587)));
test('unsupported SMTP port has no TLS mode', fn () => same(null, EndpointPolicy::tlsForPort(25)));
test('approved public endpoint is accepted', function (): void {
    $policy = new EndpointPolicy([['host' => 'mail.example.com', 'port' => 465]]);
    $result = $policy->validate('MAIL.EXAMPLE.COM.', 465, fn (): array => ['8.8.8.8']);
    same('mail.example.com', $result['host']);
});
test('private endpoint is blocked by default', function (): void {
    $policy = new EndpointPolicy([['host' => 'mail.example.com']]);
    throws(InvalidArgumentException::class, fn () => $policy->validate('mail.example.com', 587, fn (): array => ['10.0.0.1']));
});
test('explicit private endpoint exception is accepted', function (): void {
    $policy = new EndpointPolicy([['host' => 'mail.example.com', 'allow_private' => true]]);
    same('starttls', $policy->validate('mail.example.com', 587, fn (): array => ['10.0.0.1'])['tls']);
    throws(InvalidArgumentException::class, fn () => $policy->validate('mail.example.com', 587, fn (): array => ['169.254.169.254']));
});
test('unapproved endpoint is rejected', function (): void {
    $policy = new EndpointPolicy([['host' => 'mail.example.com']]);
    throws(InvalidArgumentException::class, fn () => $policy->validate('evil.example.net', 587, fn (): array => ['8.8.8.8']));
});
test('approved endpoint TLS pin is enforced', function (): void {
    $policy = new EndpointPolicy([['host' => 'mail.example.com', 'tls' => 'implicit']]);
    throws(InvalidArgumentException::class, fn () => $policy->validate('mail.example.com', 587, fn (): array => ['8.8.8.8']));
});
test('empty DNS answer is rejected', function (): void {
    $policy = new EndpointPolicy([['host' => 'mail.example.com']]);
    throws(InvalidArgumentException::class, fn () => $policy->validate('mail.example.com', 587, fn (): array => []));
});
test('IPv4 special-use ranges are blocked', function (): void {
    foreach (['127.0.0.1', '169.254.169.254', '100.100.100.200', '192.168.1.1'] as $address) {
        expect(EndpointPolicy::isBlockedAddress($address), $address . ' should be blocked.');
    }
});
test('IPv6 special-use ranges are blocked', function (): void {
    foreach (['::1', 'fe80::1', 'fd00:ec2::254', 'ff02::1'] as $address) {
        expect(EndpointPolicy::isBlockedAddress($address), $address . ' should be blocked.');
    }
});

$repository = new MemoryAccountRepository();
$servicePolicy = new EndpointPolicy([['host' => 'mail.example.com', 'port' => 587]]);
$service = new AccountService($repository, $cipher, $servicePolicy);
$validInput = [
    'label' => 'Primary sender',
    'smtp_host' => 'mail.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'sender@example.com',
    'password' => 'initial-secret',
    'from_email' => 'sender@example.com',
    'from_name' => 'Example Sender',
    'enabled' => true,
];
$publicResolver = fn (): array => ['8.8.8.8'];

test('account service creates encrypted credentials', function () use ($service, $repository, $validInput, $publicResolver): void {
    $publicId = $service->create($validInput, $publicResolver);
    expect(str_starts_with($publicId, 'acct_'));
    expect($repository->rows[$publicId]['password_enc'] !== 'initial-secret');
});
test('account service preserves a blank edit password', function () use ($service, $repository, $validInput, $publicResolver): void {
    $publicId = array_key_first($repository->rows);
    $before = $repository->rows[$publicId]['password_enc'];
    $input = $validInput;
    $input['password'] = '';
    $service->update($publicId, $input, $publicResolver);
    same($before, $repository->rows[$publicId]['password_enc']);
});
test('account service replaces a supplied edit password', function () use ($service, $repository, $validInput, $publicResolver): void {
    $publicId = array_key_first($repository->rows);
    $before = $repository->rows[$publicId]['password_enc'];
    $input = $validInput;
    $input['password'] = 'new-secret';
    $service->update($publicId, $input, $publicResolver);
    expect($before !== $repository->rows[$publicId]['password_enc']);
    same('new-secret', $service->revealPasswordForWorker($publicId));
});
test('account service requires a create password', function () use ($service, $validInput, $publicResolver): void {
    $input = $validInput;
    unset($input['password']);
    throws(InvalidArgumentException::class, fn () => $service->create($input, $publicResolver));
});
test('account service validates the From address', function () use ($service, $validInput, $publicResolver): void {
    $input = $validInput;
    $input['from_email'] = 'not-an-address';
    throws(InvalidArgumentException::class, fn () => $service->create($input, $publicResolver));
});
test('account panel view omits encrypted secrets', function () use ($service, $repository): void {
    $publicId = array_key_first($repository->rows);
    expect(!array_key_exists('password_enc', $service->view($publicId)));
});
test('account service reveals a password only on worker path', function () use ($service, $repository): void {
    $publicId = array_key_first($repository->rows);
    same('new-secret', $service->revealPasswordForWorker($publicId));
});
test('account service disables an account with a reason', function () use ($service, $repository): void {
    $publicId = array_key_first($repository->rows);
    $service->disable($publicId, 'operator pause');
    same(false, $repository->rows[$publicId]['enabled']);
    same('operator pause', $repository->rows[$publicId]['pause_reason']);
});
test('account service reports a missing delete target', function () use ($service): void {
    throws(InvalidArgumentException::class, fn () => $service->delete('acct_' . str_repeat('f', 32)));
});

$probe = new FakeSmtpProbe();
$tester = new SmtpConnectionTester($probe);
$probeConfiguration = [
    'host' => 'mail.example.com',
    'port' => 587,
    'tls' => 'starttls',
    'username' => 'sender@example.com',
    'password' => 'secret',
    'from_email' => 'sender@example.com',
];

test('SMTP tester reports success', function () use ($tester, $probe, $probeConfiguration): void {
    $probe->result = null;
    same(['ok' => true], $tester->test($probeConfiguration));
});
foreach (SmtpConnectionTester::CATEGORIES as $category) {
    test('SMTP tester safely reports ' . $category, function () use ($tester, $probe, $probeConfiguration, $category): void {
        $probe->result = $category;
        $result = $tester->test($probeConfiguration);
        same(false, $result['ok']);
        same($category, $result['category']);
        expect($result['message'] !== '');
    });
}
test('SMTP tester normalizes unknown failures', function () use ($tester, $probe, $probeConfiguration): void {
    $probe->result = 'provider-secret-detail';
    same('protocol', $tester->test($probeConfiguration)['category']);
});
test('SMTP tester rejects mismatched TLS before probing', function () use ($tester, $probe, $probeConfiguration): void {
    $probe->calls = 0;
    $configuration = $probeConfiguration;
    $configuration['tls'] = 'implicit';
    same(false, $tester->test($configuration)['ok']);
    same(0, $probe->calls);
});
test('SMTP tester clamps timeouts to one second', function () use ($tester, $probe, $probeConfiguration): void {
    $probe->result = null;
    $tester->test($probeConfiguration, 0, -1);
    same(1, $probe->connectTimeout);
    same(1, $probe->operationTimeout);
});

$passed = 0;
$failed = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        $passed++;
        fwrite(STDOUT, "ok - {$name}\n");
    } catch (Throwable $throwable) {
        $failed++;
        fwrite(STDERR, "FAIL - {$name}: {$throwable->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("Passed: %d, Failed: %d\n", $passed, $failed));
exit($failed === 0 ? 0 : 1);
