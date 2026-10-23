<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\AdminSession;
use App\Auth\IssuedAdminSession;
use App\Auth\LoginResult;
use App\Repositories\AdministratorRepository;
use App\Repositories\AdministratorSessionRepository;
use App\Repositories\AuditRepository;
use App\Repositories\LoginThrottleRepository;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;
use PDOException;
use Throwable;

final readonly class AuthService
{
    public function __construct(
        private PDO $pdo,
        private AdministratorRepository $administrators,
        private AdministratorSessionRepository $sessions,
        private LoginThrottleRepository $throttles,
        private AuditRepository $audit,
        private PasswordHasher $hasher,
        private PasswordPolicy $passwordPolicy,
        private string $throttleKey,
        private int $idleSeconds,
        private int $absoluteSeconds,
        private int $loginWindowSeconds,
        private int $loginMaximumFailures,
        private int $loginBlockSeconds,
    ) {
        if (strlen($this->throttleKey) < 32) {
            throw new \InvalidArgumentException('Authentication throttle key must contain at least 32 bytes.');
        }
    }

    public function login(
        string $email,
        string $password,
        string $ipAddress,
        string $userAgent,
        ?string $requestId = null,
        ?string $existingCookieToken = null,
        ?DateTimeImmutable $now = null,
    ): LoginResult {
        $now = $this->utc($now);
        $normalizedEmail = $this->normalizeEmailForLookup($email);
        $scopeHashes = $this->scopeHashes($normalizedEmail, $ipAddress);
        $retryAfter = max(array_map(fn (string $scope): int => $this->throttles->retryAfter($scope, $now), $scopeHashes));
        if ($retryAfter > 0) {
            $this->audit->record('login_throttled', null, $requestId, $this->ipHash($ipAddress), self::format($now));
            return LoginResult::failure($retryAfter);
        }

        $administrator = $this->validEmail($normalizedEmail)
            ? $this->administrators->findByEmail($normalizedEmail)
            : null;
        $verificationHash = is_array($administrator)
            ? (string) $administrator['password_hash']
            : $this->administrators->anyPasswordHash();
        if ($verificationHash === null) {
            $verificationHash = $this->hasher->hash('mailpanel-dummy-password-never-used');
        }
        $passwordMatches = $this->hasher->verify(strlen($password) <= 1024 ? $password : '', $verificationHash);
        $enabled = is_array($administrator) && $administrator['disabled_at'] === null;

        if (!$passwordMatches || !$enabled) {
            $retryAfter = 0;
            foreach ($scopeHashes as $scopeHash) {
                $retryAfter = max($retryAfter, $this->throttles->recordFailure(
                    $scopeHash,
                    $now,
                    $this->loginWindowSeconds,
                    $this->loginMaximumFailures,
                    $this->loginBlockSeconds,
                ));
            }
            $this->audit->record(
                $retryAfter > 0 ? 'login_throttled' : 'login_failed',
                is_array($administrator) ? (int) $administrator['id'] : null,
                $requestId,
                $this->ipHash($ipAddress),
                self::format($now),
            );
            return LoginResult::failure($retryAfter);
        }

        $administratorId = (int) $administrator['id'];
        if ($this->hasher->needsRehash((string) $administrator['password_hash'])) {
            $updated = $this->administrators->updateHashIfCurrent(
                $administratorId,
                (string) $administrator['password_hash'],
                $this->hasher->hash($password),
                self::format($now),
            );
            if (!$updated) {
                $this->audit->record('login_failed', $administratorId, $requestId, $this->ipHash($ipAddress), self::format($now));
                return LoginResult::failure();
            }
        }
        $this->throttles->clear($scopeHashes);
        if (is_string($existingCookieToken) && $this->validSessionToken($existingCookieToken)) {
            $this->sessions->revokeByTokenHash(hash('sha256', $existingCookieToken), self::format($now));
        }
        $this->administrators->recordLogin($administratorId, self::format($now));
        $issued = $this->issueSession(
            $administratorId,
            (string) $administrator['email'],
            (int) $administrator['security_version'],
            $ipAddress,
            $userAgent,
            $now,
        );
        $this->audit->record('login_succeeded', $administratorId, $requestId, $this->ipHash($ipAddress), self::format($now));

        return LoginResult::success($issued);
    }

    public function authenticateSession(
        string $cookieToken,
        string $ipAddress = '',
        ?string $requestId = null,
        ?DateTimeImmutable $now = null,
    ): ?AdminSession {
        if (!$this->validSessionToken($cookieToken)) {
            return null;
        }
        $now = $this->utc($now);
        $row = $this->sessions->findByTokenHash(hash('sha256', $cookieToken));
        if ($row === null || $row['revoked_at'] !== null || $row['disabled_at'] !== null) {
            return null;
        }

        $idleExpiry = $this->timestamp((string) $row['idle_expires_at']);
        $absoluteExpiry = $this->timestamp((string) $row['absolute_expires_at']);
        $versionMatches = (int) $row['security_version'] === (int) $row['administrator_security_version'];
        if (!$versionMatches || $idleExpiry <= $now->getTimestamp() || $absoluteExpiry <= $now->getTimestamp()) {
            $this->sessions->revokeById((int) $row['id'], self::format($now));
            if ($versionMatches) {
                $this->audit->record(
                    'session_expired',
                    (int) $row['administrator_id'],
                    $requestId,
                    $this->ipHash($ipAddress),
                    self::format($now),
                );
            }
            return null;
        }

        $newIdleExpiry = min($absoluteExpiry, $now->getTimestamp() + $this->idleSeconds);
        $this->sessions->touch((int) $row['id'], self::format($now), self::formatTimestamp($newIdleExpiry));

        return new AdminSession(
            (int) $row['id'],
            (int) $row['administrator_id'],
            (string) $row['email'],
            (int) $row['security_version'],
            (string) $row['csrf_secret'],
            $absoluteExpiry,
        );
    }

    public function logout(
        AdminSession $session,
        string $ipAddress = '',
        ?string $requestId = null,
        ?DateTimeImmutable $now = null,
    ): void {
        $now = $this->utc($now);
        $this->sessions->revokeById($session->sessionId, self::format($now));
        $this->audit->record('logout', $session->administratorId, $requestId, $this->ipHash($ipAddress), self::format($now));
    }

    public function changePassword(
        AdminSession $session,
        string $currentPassword,
        string $newPassword,
        string $confirmation,
        string $ipAddress,
        string $userAgent,
        ?string $requestId = null,
        ?DateTimeImmutable $now = null,
    ): IssuedAdminSession {
        if (!hash_equals($newPassword, $confirmation)) {
            throw new DomainException('New password confirmation does not match.');
        }
        $this->passwordPolicy->validate($newPassword);
        $existing = $this->administrators->findById($session->administratorId);
        if ($existing === null || !$this->hasher->verify($currentPassword, (string) $existing['password_hash'])) {
            throw new DomainException('Current password is incorrect.');
        }
        if ($this->hasher->verify($newPassword, (string) $existing['password_hash'])) {
            throw new DomainException('New password must differ from the current password.');
        }

        $newHash = $this->hasher->hash($newPassword);
        $now = $this->utc($now);
        $this->pdo->beginTransaction();
        try {
            $locked = $this->administrators->lockById($session->administratorId);
            if ($locked === null
                || !hash_equals((string) $existing['password_hash'], (string) $locked['password_hash'])
                || (int) $locked['security_version'] !== $session->securityVersion
                || $locked['disabled_at'] !== null) {
                throw new DomainException('Administrator credentials changed; sign in again.');
            }
            $newVersion = $this->administrators->changePassword($session->administratorId, $newHash, self::format($now));
            $this->sessions->revokeAllForAdministrator($session->administratorId, self::format($now));
            $issued = $this->issueSession(
                $session->administratorId,
                $session->email,
                $newVersion,
                $ipAddress,
                $userAgent,
                $now,
            );
            $this->audit->record('password_changed', $session->administratorId, $requestId, $this->ipHash($ipAddress), self::format($now));
            $this->pdo->commit();
            return $issued;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    public function createAdministrator(string $email, string $password, ?DateTimeImmutable $now = null): int
    {
        $email = $this->normalizeAndValidateEmail($email);
        $this->passwordPolicy->validate($password);
        $passwordHash = $this->hasher->hash($password);
        $now = $this->utc($now);
        $this->pdo->beginTransaction();
        try {
            if ($this->administrators->count() !== 0) {
                throw new DomainException('An administrator already exists.');
            }
            try {
                $id = $this->administrators->create($email, $passwordHash, self::format($now));
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    throw new DomainException('An administrator already exists.', 0, $exception);
                }
                throw $exception;
            }
            $this->audit->record('administrator_created', $id, null, null, self::format($now));
            $this->pdo->commit();
            return $id;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    public function resetAdministratorPassword(string $email, string $password, ?DateTimeImmutable $now = null): void
    {
        $email = $this->normalizeAndValidateEmail($email);
        $this->passwordPolicy->validate($password);
        $newHash = $this->hasher->hash($password);
        $administrator = $this->administrators->findByEmail($email);
        if ($administrator === null) {
            throw new DomainException('Administrator does not exist.');
        }

        $now = $this->utc($now);
        $this->pdo->beginTransaction();
        try {
            $locked = $this->administrators->lockById((int) $administrator['id']);
            if ($locked === null) {
                throw new DomainException('Administrator does not exist.');
            }
            $this->administrators->changePassword((int) $administrator['id'], $newHash, self::format($now));
            $this->sessions->revokeAllForAdministrator((int) $administrator['id'], self::format($now));
            $this->audit->record('administrator_password_reset', (int) $administrator['id'], null, null, self::format($now));
            $this->pdo->commit();
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    public function ipHash(string $ipAddress): ?string
    {
        $ipAddress = trim($ipAddress);
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        return hash_hmac('sha256', "ip\0" . $ipAddress, $this->throttleKey);
    }

    private function issueSession(
        int $administratorId,
        string $email,
        int $securityVersion,
        string $ipAddress,
        string $userAgent,
        DateTimeImmutable $now,
    ): IssuedAdminSession {
        $token = self::base64UrlEncode(random_bytes(32));
        $csrfSecret = bin2hex(random_bytes(32));
        $absoluteExpiry = $now->getTimestamp() + $this->absoluteSeconds;
        $idleExpiry = min($absoluteExpiry, $now->getTimestamp() + $this->idleSeconds);
        $sessionId = $this->sessions->create([
            'administrator_id' => $administratorId,
            'token_hash' => hash('sha256', $token),
            'csrf_secret' => $csrfSecret,
            'security_version' => $securityVersion,
            'ip_hash' => $this->ipHash($ipAddress),
            'user_agent_hash' => $userAgent === '' ? null : hash_hmac('sha256', "ua\0" . $userAgent, $this->throttleKey),
            'created_at' => self::format($now),
            'last_seen_at' => self::format($now),
            'idle_expires_at' => self::formatTimestamp($idleExpiry),
            'absolute_expires_at' => self::formatTimestamp($absoluteExpiry),
        ]);

        return new IssuedAdminSession(
            $token,
            new AdminSession($sessionId, $administratorId, $email, $securityVersion, $csrfSecret, $absoluteExpiry),
        );
    }

    /** @return list<string> */
    private function scopeHashes(string $email, string $ipAddress): array
    {
        $ip = filter_var(trim($ipAddress), FILTER_VALIDATE_IP) === false ? 'unknown' : trim($ipAddress);
        return [
            hash_hmac('sha256', "identifier\0" . substr($email, 0, 254), $this->throttleKey),
            hash_hmac('sha256', "ip\0" . $ip, $this->throttleKey),
        ];
    }

    private function normalizeAndValidateEmail(string $email): string
    {
        $email = $this->normalizeEmailForLookup($email);
        if (!$this->validEmail($email)) {
            throw new DomainException('Administrator email address is invalid.');
        }
        return $email;
    }

    private function normalizeEmailForLookup(string $email): string
    {
        return strtolower(trim($email));
    }

    private function validEmail(string $email): bool
    {
        return strlen($email) <= 254
            && preg_match('/^[\x20-\x7E]+$/', $email) === 1
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validSessionToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }

    private function utc(?DateTimeImmutable $now): DateTimeImmutable
    {
        return ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
    }

    private function timestamp(string $value): int
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
    }

    private static function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function formatTimestamp(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
