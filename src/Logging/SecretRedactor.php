<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

final class SecretRedactor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->sanitize($record->context),
            extra: $this->sanitize($record->extra),
        );
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/(?:authorization|password|secret|token|keyset|(?:^|_)key(?:_|$)|fingerprint|body|content|dsn)/i', (string) $key) === 1) {
                $values[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $values[$key] = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $value) ?? '[redacted]';
            }
        }

        return $values;
    }
}
