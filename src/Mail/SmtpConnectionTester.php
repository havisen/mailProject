<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Adapter contract for a connect-and-authenticate probe. Implementations must
 * verify TLS and must not send DATA.
 */
interface SmtpProbe
{
    /**
     * @param array{host:string,port:int,tls:string,username:string,password:string,from_email:string} $configuration
     */
    public function probe(array $configuration, int $connectTimeoutSeconds, int $operationTimeoutSeconds): ?string;
}

final class SmtpConnectionTester
{
    public const CATEGORIES = [
        'dns',
        'connectivity',
        'tls',
        'auth',
        'sender_auth',
        'quota',
        'protocol',
    ];

    public function __construct(private SmtpProbe $probe)
    {
    }

    /**
     * @param array{host:string,port:int,tls:string,username:string,password:string,from_email:string} $configuration
     * @return array{ok:bool,category?:string,message?:string}
     */
    public function test(
        array $configuration,
        int $connectTimeoutSeconds = 10,
        int $operationTimeoutSeconds = 20,
    ): array {
        $tls = $configuration['tls'] ?? '';
        $port = (int) ($configuration['port'] ?? 0);
        if (!in_array($tls, ['implicit', 'starttls'], true)) {
            return $this->failure('protocol', 'TLS mode must be implicit or starttls.');
        }
        if (($port === 465 && $tls !== 'implicit') || ($port === 587 && $tls !== 'starttls')) {
            return $this->failure('protocol', 'The SMTP port and TLS mode do not match.');
        }
        if (!in_array($port, [465, 587], true)) {
            return $this->failure('protocol', 'SMTP port must be 465 or 587.');
        }

        $category = $this->probe->probe(
            $configuration,
            max(1, $connectTimeoutSeconds),
            max(1, $operationTimeoutSeconds)
        );
        if ($category === null) {
            return ['ok' => true];
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'protocol';
        }

        return $this->failure($category, $this->safeMessage($category));
    }

    /** @return array{ok:false,category:string,message:string} */
    private function failure(string $category, string $message): array
    {
        return ['ok' => false, 'category' => $category, 'message' => $message];
    }

    private function safeMessage(string $category): string
    {
        return match ($category) {
            'dns' => 'The SMTP hostname did not resolve. Check DNS for the approved endpoint.',
            'connectivity' => 'Could not connect to the SMTP endpoint. Check the network, port, and provider status.',
            'tls' => 'TLS handshake or certificate verification failed. Certificate checks are never bypassed.',
            'auth' => 'The SMTP server rejected the username or password.',
            'sender_auth' => 'The SMTP server rejected the configured sender identity.',
            'quota' => 'The SMTP server reported a sending limit or quota.',
            default => 'The SMTP server returned an unexpected response.',
        };
    }
}
