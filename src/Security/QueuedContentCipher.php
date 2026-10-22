<?php

declare(strict_types=1);

namespace App\Security;

/** Encrypts queued private content with keys stored outside the database. */
final class QueuedContentCipher
{
    private CredentialCipher $cipher;

    /** @param array<string, string> $keys */
    public function __construct(array $keys, string $activeKeyId)
    {
        $this->cipher = new CredentialCipher($keys, $activeKeyId);
    }

    public function encryptSnapshot(string $submissionId, string $json): string
    {
        return $this->cipher->encrypt($json, $this->context($submissionId, 'snapshot'));
    }

    public function decryptSnapshot(string $submissionId, string $ciphertext): string
    {
        return $this->cipher->decrypt($ciphertext, $this->context($submissionId, 'snapshot'));
    }

    public function encryptRecipient(string $submissionId, int $ordinal, string $address): string
    {
        return $this->cipher->encrypt($address, $this->context($submissionId, 'recipient-' . $ordinal));
    }

    public function decryptRecipient(string $submissionId, int $ordinal, string $ciphertext): string
    {
        return $this->cipher->decrypt($ciphertext, $this->context($submissionId, 'recipient-' . $ordinal));
    }

    private function context(string $submissionId, string $field): string
    {
        if (preg_match('/^sub_[a-f0-9]{32}$/', $submissionId) !== 1) {
            throw new \InvalidArgumentException('Invalid submission ID for encryption context.');
        }
        return 'submission:' . $submissionId . ':' . $field;
    }
}
