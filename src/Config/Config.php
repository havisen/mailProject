<?php

declare(strict_types=1);

namespace App\Config;

final readonly class Config
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key): string
    {
        $value = $this->get($key);
        if (!is_string($value) || $value === '') {
            throw new ConfigException(sprintf('Configuration value %s is unavailable.', $key));
        }

        return $value;
    }

    public function int(string $key): int
    {
        $value = $this->get($key);
        if (!is_int($value)) {
            throw new ConfigException(sprintf('Configuration value %s is unavailable.', $key));
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);
        if (!is_bool($value)) {
            throw new ConfigException(sprintf('Configuration value %s is unavailable.', $key));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    /** @return array<string, mixed> */
    public function redacted(): array
    {
        return $this->redact($this->values);
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/(?:password|secret|keyset|fingerprint_key|token|authorization|dsn)/i', (string) $key) === 1) {
                $values[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
