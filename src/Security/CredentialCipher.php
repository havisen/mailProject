<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Versioned authenticated encryption for stored mail-account credentials.
 *
 * Stored format: v1.<key-id>.<nonce>.<ciphertext>
 *
 * Keys are supplied as base64-encoded 32-byte values and must be kept outside
 * the database and web document root. A context string can bind a ciphertext
 * to a specific account and field. Context binding is implemented by deriving
 * a per-context secretbox key with keyed BLAKE2b.
 */
final class CredentialCipher
{
    public const CURRENT_VERSION = 'v1';
    public const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    public const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    /** @var array<string, string> */
    private array $keys = [];

    /**
     * @param array<string, string> $keys Map of key ID to base64-encoded key.
     */
    public function __construct(array $keys, private string $activeKeyId)
    {
        if ($keys === []) {
            throw new \InvalidArgumentException('CredentialCipher requires at least one master key.');
        }

        foreach ($keys as $id => $encodedKey) {
            if (!is_string($id) || preg_match('/^[A-Za-z0-9-]{1,32}$/', $id) !== 1) {
                throw new \InvalidArgumentException('Invalid credential key ID.');
            }

            $rawKey = base64_decode($encodedKey, true);
            if ($rawKey === false || strlen($rawKey) !== self::KEY_BYTES) {
                throw new \InvalidArgumentException(
                    sprintf('Credential key %s must decode to exactly %d bytes.', $id, self::KEY_BYTES)
                );
            }

            $this->keys[$id] = $rawKey;
        }

        if (!isset($this->keys[$this->activeKeyId])) {
            throw new \InvalidArgumentException('The active credential key ID is not present in the key set.');
        }
    }

    public function activeKeyId(): string
    {
        return $this->activeKeyId;
    }

    public function encrypt(string $plaintext, string $context = ''): string
    {
        if ($plaintext === '') {
            throw new \InvalidArgumentException('Refusing to encrypt an empty credential.');
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_secretbox(
            $plaintext,
            $nonce,
            $this->keyForContext($this->keys[$this->activeKeyId], $context)
        );

        return implode('.', [
            self::CURRENT_VERSION,
            $this->activeKeyId,
            self::base64UrlEncode($nonce),
            self::base64UrlEncode($ciphertext),
        ]);
    }

    public function decrypt(string $storedValue, string $context = ''): string
    {
        $parts = explode('.', $storedValue);
        if (count($parts) !== 4) {
            throw new \RuntimeException('Malformed encrypted credential.');
        }

        [$version, $keyId, $encodedNonce, $encodedCiphertext] = $parts;
        if ($version !== self::CURRENT_VERSION) {
            throw new \RuntimeException('Unsupported encrypted credential version.');
        }

        if (!isset($this->keys[$keyId])) {
            throw new \RuntimeException('The credential key ID is unavailable.');
        }

        $nonce = self::base64UrlDecode($encodedNonce);
        if (strlen($nonce) !== self::NONCE_BYTES) {
            throw new \RuntimeException('Malformed encrypted credential nonce.');
        }

        $plaintext = sodium_crypto_secretbox_open(
            self::base64UrlDecode($encodedCiphertext),
            $nonce,
            $this->keyForContext($this->keys[$keyId], $context)
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Credential authentication failed.');
        }

        return $plaintext;
    }

    public static function contextFor(string $publicAccountId, string $field): string
    {
        if (preg_match('/^acct_[a-f0-9]{32}$/', $publicAccountId) !== 1) {
            throw new \InvalidArgumentException('Invalid account public ID for credential context.');
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $field) !== 1) {
            throw new \InvalidArgumentException('Invalid credential field name.');
        }

        return sprintf('acct:%s:%s', $publicAccountId, $field);
    }

    private function keyForContext(string $masterKey, string $context): string
    {
        if ($context === '') {
            return $masterKey;
        }

        return sodium_crypto_generichash(
            "mail-panel-credential-context\0" . $context,
            $masterKey,
            self::KEY_BYTES
        );
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new \RuntimeException('Malformed base64url credential segment.');
        }

        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Malformed base64url credential segment.');
        }

        return $decoded;
    }
}
