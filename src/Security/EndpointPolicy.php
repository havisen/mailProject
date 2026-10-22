<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Enforces the operator-controlled allow-list for SMTP destinations.
 */
final class EndpointPolicy
{
    /**
     * @param array<int, array{host:string,port?:int,tls?:string,allow_private?:bool}> $approvedEndpoints
     */
    public function __construct(private array $approvedEndpoints)
    {
    }

    public static function tlsForPort(int $port): ?string
    {
        return match ($port) {
            465 => 'implicit',
            587 => 'starttls',
            default => null,
        };
    }

    /**
     * @param callable(string):array<int, string>|null $resolver
     * @return array{host:string,port:int,tls:string}
     */
    public function validate(string $host, int $port, ?callable $resolver = null): array
    {
        $host = strtolower(rtrim(trim($host), '.'));
        if (!$this->isValidHostname($host)) {
            throw new \InvalidArgumentException('SMTP host is empty or is not a valid hostname.');
        }

        $tls = self::tlsForPort($port);
        if ($tls === null) {
            throw new \InvalidArgumentException('SMTP port must be 465 (implicit TLS) or 587 (STARTTLS).');
        }

        $matched = null;
        foreach ($this->approvedEndpoints as $endpoint) {
            if (!isset($endpoint['host']) || strcasecmp(rtrim($endpoint['host'], '.'), $host) !== 0) {
                continue;
            }

            if (isset($endpoint['port']) && (int) $endpoint['port'] !== $port) {
                continue;
            }

            if (isset($endpoint['tls']) && $endpoint['tls'] !== $tls) {
                throw new \InvalidArgumentException('The approved SMTP endpoint requires a different TLS mode.');
            }

            $matched = $endpoint;
            break;
        }

        if ($matched === null) {
            throw new \InvalidArgumentException('SMTP host is not on the operator-approved endpoint list.');
        }

        $resolver ??= static function (string $hostname): array {
            $addresses = [];
            $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);
            if (!is_array($records)) {
                return [];
            }

            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $addresses[] = $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }

            return array_values(array_unique($addresses));
        };

        $addresses = $resolver($host);
        if ($addresses === []) {
            throw new \InvalidArgumentException('SMTP host did not resolve to an address.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                throw new \InvalidArgumentException('SMTP host returned an invalid network address.');
            }

            if (
                self::isBlockedAddress($address)
                && (empty($matched['allow_private']) || !self::isNarrowPrivateAddress($address))
            ) {
                throw new \InvalidArgumentException('SMTP host resolves to a disallowed network address.');
            }
        }

        return ['host' => $host, 'port' => $port, 'tls' => $tls];
    }

    public static function isBlockedAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        $blockedRanges = str_contains($address, ':')
            ? [
                '::/128',
                '::1/128',
                '::ffff:0:0/96',
                '100::/64',
                '2001:db8::/32',
                'fc00::/7',
                'fe80::/10',
                'ff00::/8',
            ]
            : [
                '0.0.0.0/8',
                '10.0.0.0/8',
                '100.64.0.0/10',
                '127.0.0.0/8',
                '169.254.0.0/16',
                '172.16.0.0/12',
                '192.0.0.0/24',
                '192.0.2.0/24',
                '192.168.0.0/16',
                '198.18.0.0/15',
                '198.51.100.0/24',
                '203.0.113.0/24',
                '224.0.0.0/4',
                '240.0.0.0/4',
            ];

        foreach ($blockedRanges as $range) {
            if (self::addressInRange($address, $range)) {
                return true;
            }
        }

        return false;
    }

    private function isValidHostname(string $host): bool
    {
        if ($host === '' || strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if (
                $label === ''
                || strlen($label) > 63
                || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $label) !== 1
            ) {
                return false;
            }
        }

        return true;
    }

    private static function addressInRange(string $address, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range, 2);
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton($network);

        if (
            $addressBytes === false
            || $networkBytes === false
            || strlen($addressBytes) !== strlen($networkBytes)
        ) {
            return false;
        }

        $remainingBits = (int) $prefixLength;
        for ($i = 0, $length = strlen($addressBytes); $i < $length; $i++) {
            if ($remainingBits <= 0) {
                return true;
            }

            $bits = min(8, $remainingBits);
            $mask = (0xff << (8 - $bits)) & 0xff;
            if ((ord($addressBytes[$i]) & $mask) !== (ord($networkBytes[$i]) & $mask)) {
                return false;
            }

            $remainingBits -= $bits;
        }

        return true;
    }

    private static function isNarrowPrivateAddress(string $address): bool
    {
        $privateRanges = str_contains($address, ':')
            ? ['fc00::/7']
            : ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];

        foreach ($privateRanges as $range) {
            if (self::addressInRange($address, $range)) {
                return true;
            }
        }

        return false;
    }
}
