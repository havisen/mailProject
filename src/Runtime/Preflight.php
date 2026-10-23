<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Config\Config;
use PDO;
use Throwable;

final readonly class Preflight
{
    public function __construct(private Config $config, private ?PDO $pdo)
    {
    }

    /** @return list<PreflightResult> */
    public function run(): array
    {
        $results = [];
        $results[] = new PreflightResult('PHP version', PHP_VERSION_ID >= 80400, PHP_VERSION_ID >= 80400 ? PHP_VERSION : 'PHP 8.4 or newer is required.');

        foreach (['json', 'intl', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'sodium'] as $extension) {
            $loaded = extension_loaded($extension);
            $results[] = new PreflightResult('Extension ' . $extension, $loaded, $loaded ? 'loaded' : 'missing');
        }

        $argon2id = defined('PASSWORD_ARGON2ID');
        $results[] = new PreflightResult(
            'Argon2id password hashing',
            $argon2id,
            $argon2id ? 'available' : 'PASSWORD_ARGON2ID is unavailable',
        );

        foreach (['storage', 'logs'] as $name) {
            $path = $this->config->string('paths.' . $name);
            $usable = is_dir($path) && is_readable($path) && is_writable($path);
            $results[] = new PreflightResult('Private ' . $name . ' path', $usable, $usable ? 'readable and writable' : 'missing or not writable');
        }

        $caBundle = $this->config->get('mail.tls_ca_bundle');
        if (is_string($caBundle) && $caBundle !== '') {
            $usable = is_file($caBundle) && is_readable($caBundle);
            $results[] = new PreflightResult('TLS CA bundle', $usable, $usable ? 'configured and readable' : 'configured path is not readable');
        } else {
            $locations = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
            $candidates = array_filter([
                $locations['ini_cafile'] ?? null,
                $locations['default_cert_file'] ?? null,
            ], static fn (mixed $candidate): bool => is_string($candidate) && $candidate !== '');
            $usable = array_any($candidates, static fn (string $candidate): bool => is_file($candidate) && is_readable($candidate));
            $results[] = new PreflightResult('TLS trust store', $usable, $usable ? 'OpenSSL default is readable' : 'no readable default CA file; configure MAILPANEL_TLS_CA_BUNDLE');
        }

        if ($this->pdo === null) {
            $results[] = new PreflightResult('Database', false, 'connection unavailable', 'database');
        } else {
            try {
                $version = (string) $this->pdo->query('SELECT VERSION()')->fetchColumn();
                $results[] = new PreflightResult('Database', $version !== '', $version !== '' ? 'connection and query succeeded' : 'version query returned no value', 'database');
            } catch (Throwable) {
                $results[] = new PreflightResult('Database', false, 'connection or query failed', 'database');
            }
        }

        return $results;
    }
}
