<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AccountRepository;
use App\Repositories\JobRepository;
use App\Security\CredentialCipher;
use App\Security\EndpointPolicy;

final class AccountService implements SendingAccountProvider
{
    public function __construct(
        private AccountRepository $accounts,
        private CredentialCipher $cipher,
        private EndpointPolicy $endpointPolicy,
        private ?JobRepository $jobs = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param callable(string):array<int, string>|null $resolver
     */
    public function create(array $input, ?callable $resolver = null): string
    {
        $account = $this->validate($input, true, $resolver);
        $publicId = $this->newPublicId();
        $context = CredentialCipher::contextFor($publicId, 'password');
        $now = gmdate('Y-m-d H:i:s');

        $account['public_id'] = $publicId;
        $account['password_enc'] = $this->cipher->encrypt((string) $input['password'], $context);
        $account['password_aad'] = $context;
        $account['password_changed_at'] = $now;
        $account['created_at'] = $now;
        $account['updated_at'] = $now;
        $this->accounts->insert($account);

        return $publicId;
    }

    /**
     * @param array<string, mixed> $input
     * @param callable(string):array<int, string>|null $resolver
     */
    public function update(string $publicId, array $input, ?callable $resolver = null): void
    {
        if ($this->accounts->findWithSecretByPublicId($publicId) === null) {
            throw new \InvalidArgumentException('Account not found.');
        }

        $account = $this->validate($input, false, $resolver);
        $password = $input['password'] ?? null;
        if (is_string($password) && $password !== '') {
            $context = CredentialCipher::contextFor($publicId, 'password');
            $account['password_enc'] = $this->cipher->encrypt($password, $context);
            $account['password_aad'] = $context;
            $account['password_changed_at'] = gmdate('Y-m-d H:i:s');
        }

        $this->accounts->update($publicId, $account);
    }

    public function disable(string $publicId, string $reason = 'Disabled by administrator'): void
    {
        if ($this->accounts->findByPublicId($publicId) === null) {
            throw new \InvalidArgumentException('Account not found.');
        }

        $this->accounts->disable($publicId, $reason);
    }

    public function delete(string $publicId): void
    {
        if (!$this->accounts->delete($publicId)) {
            throw new \InvalidArgumentException('Account not found.');
        }
    }

    public function revealPasswordForWorker(string $publicId): string
    {
        $account = $this->accounts->findWithSecretByPublicId($publicId);
        if ($account === null) {
            throw new \InvalidArgumentException('Account not found.');
        }

        $context = (string) ($account['password_aad'] ?? '');
        if ($context === '') {
            $context = CredentialCipher::contextFor($publicId, 'password');
        }

        return $this->cipher->decrypt((string) $account['password_enc'], $context);
    }

    /** @return array<string, mixed>|null */
    public function configurationForWorker(string $accountId): ?array
    {
        $account = $this->accounts->findWithSecretByPublicId($accountId);
        if ($account === null || empty($account['enabled'])) {
            return null;
        }
        $context = (string) ($account['password_aad'] ?? '');
        if ($context === '') {
            $context = CredentialCipher::contextFor($accountId, 'password');
        }
        $account['password'] = $this->cipher->decrypt((string) $account['password_enc'], $context);
        unset($account['password_enc'], $account['password_aad']);
        return $account;
    }

    public function recordAuthenticationFailure(string $accountId): void
    {
        if ($this->jobs === null) {
            return;
        }
        $since = gmdate('Y-m-d H:i:s', time() - 900);
        if ($this->jobs->countRecentAccountFailures($accountId, 'authentication', $since) + 1 >= 3) {
            $this->accounts->disable($accountId, 'Paused after repeated SMTP authentication failures.');
        }
    }

    /** @return array<string, mixed> */
    public function view(string $publicId): array
    {
        $account = $this->accounts->findByPublicId($publicId);
        if ($account === null) {
            throw new \InvalidArgumentException('Account not found.');
        }

        return $account;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->accounts->listForPanel();
    }

    /**
     * @param array<string, mixed> $input
     * @param callable(string):array<int, string>|null $resolver
     * @return array<string, mixed>
     */
    private function validate(array $input, bool $isCreate, ?callable $resolver): array
    {
        $errors = [];
        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '' || $this->textLength($label) > 120) {
            $errors[] = 'Label is required and must be at most 120 characters.';
        }

        $host = trim((string) ($input['smtp_host'] ?? ''));
        $port = (int) ($input['smtp_port'] ?? 0);
        $tls = null;
        try {
            $endpoint = $this->endpointPolicy->validate($host, $port, $resolver);
            $host = $endpoint['host'];
            $tls = $endpoint['tls'];
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }

        $username = trim((string) ($input['smtp_username'] ?? ''));
        if ($username === '' || $this->textLength($username) > 255) {
            $errors[] = 'SMTP username is required and must be at most 255 characters.';
        }

        if ($isCreate && (string) ($input['password'] ?? '') === '') {
            $errors[] = 'Password is required when creating an account.';
        }

        $fromEmail = trim((string) ($input['from_email'] ?? ''));
        if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false || strlen($fromEmail) > 254) {
            $errors[] = 'From address is not a valid email address.';
        }

        $fromName = trim((string) ($input['from_name'] ?? ''));
        if ($this->textLength($fromName) > 120) {
            $errors[] = 'From name must be at most 120 characters.';
        }

        $replyToEmail = trim((string) ($input['reply_to_email'] ?? ''));
        if (
            $replyToEmail !== ''
            && (filter_var($replyToEmail, FILTER_VALIDATE_EMAIL) === false || strlen($replyToEmail) > 254)
        ) {
            $errors[] = 'Reply-To must be a valid email address or empty.';
        }

        $maxRecipients = (int) ($input['max_recipients'] ?? 10);
        $maxHourlyRecipients = (int) ($input['max_hourly_recipients'] ?? 100);
        $maxOutboundBytes = (int) ($input['max_outbound_bytes'] ?? 20971520);
        if ($maxRecipients < 1 || $maxRecipients > 10) {
            $errors[] = 'Maximum recipients per message must be between 1 and 10.';
        }
        if ($maxHourlyRecipients < 1 || $maxHourlyRecipients > 100000) {
            $errors[] = 'Maximum hourly recipients must be between 1 and 100000.';
        }
        if ($maxOutboundBytes < 1024 || $maxOutboundBytes > 104857600) {
            $errors[] = 'Maximum outbound size must be between 1 KiB and 100 MiB.';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException('Account validation failed: ' . implode(' ', $errors));
        }

        return [
            'label' => $label,
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_tls' => $tls,
            'smtp_username' => $username,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to_email' => $replyToEmail === '' ? null : $replyToEmail,
            'enabled' => array_key_exists('enabled', $input) ? !empty($input['enabled']) : true,
            'pause_reason' => null,
            'max_recipients' => $maxRecipients,
            'max_hourly_recipients' => $maxHourlyRecipients,
            'max_outbound_bytes' => $maxOutboundBytes,
        ];
    }

    private function newPublicId(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $publicId = 'acct_' . bin2hex(random_bytes(16));
            if (!$this->accounts->existsPublicId($publicId)) {
                return $publicId;
            }
        }

        throw new \RuntimeException('Could not allocate a unique account public ID.');
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
