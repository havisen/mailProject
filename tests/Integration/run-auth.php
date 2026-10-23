<?php

declare(strict_types=1);

use App\Repositories\AdministratorRepository;
use App\Repositories\AdministratorSessionRepository;
use App\Repositories\AuditRepository;
use App\Repositories\LoginThrottleRepository;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Services\AuthService;

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "SKIP: Composer dependencies are not installed.\n");
    exit(77);
}
require $autoload;

if (getenv('MAILPANEL_TEST_ALLOW_RESET') !== '1') {
    fwrite(STDERR, "SKIP: set MAILPANEL_TEST_ALLOW_RESET=1 for a disposable database.\n");
    exit(77);
}
$dsn = getenv('MAILPANEL_TEST_DB_DSN');
$user = getenv('MAILPANEL_TEST_DB_USER');
$password = getenv('MAILPANEL_TEST_DB_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user)) {
    fwrite(STDERR, "SKIP: disposable database variables are not configured.\n");
    exit(77);
}

$pdo = new PDO($dsn, $user, is_string($password) ? $password : '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$drop = static function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['audit_events', 'administrator_sessions', 'administrator_login_throttles', 'administrators'] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $table);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
};

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertThrows = static function (callable $callback, string $message) use (&$assertions): void {
    ++$assertions;
    try {
        $callback();
    } catch (DomainException|InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
};

try {
    $drop($pdo);
    (require dirname(__DIR__, 2) . '/database/migrations/001_create_admin_and_jobs.php')($pdo);
    (require dirname(__DIR__, 2) . '/database/migrations/006_create_audit_events.php')($pdo);

    $admins = new AdministratorRepository($pdo);
    $sessions = new AdministratorSessionRepository($pdo);
    $throttles = new LoginThrottleRepository($pdo);
    $audit = new AuditRepository($pdo);
    $hasher = new PasswordHasher();
    $service = new AuthService(
        $pdo,
        $admins,
        $sessions,
        $throttles,
        $audit,
        $hasher,
        new PasswordPolicy(14, 1024),
        str_repeat('t', 32),
        600,
        1800,
        900,
        5,
        900,
    );

    $start = new DateTimeImmutable('2026-01-01T00:00:00Z');
    $administratorId = $service->createAdministrator('Admin@Example.test', 'correct horse battery staple', $start);
    $assert($administratorId > 0, 'Administrator was not created.');
    $row = $admins->findByEmail('admin@example.test');
    $assert(is_array($row) && str_starts_with((string) $row['password_hash'], '$argon2id$'), 'Administrator hash is not Argon2id.');
    $assertThrows(
        static fn () => $service->createAdministrator('other@example.test', 'another correct horse password', $start),
        'A second administrator was allowed.'
    );

    for ($attempt = 1; $attempt <= 5; ++$attempt) {
        $failure = $service->login(
            'admin@example.test',
            'wrong password',
            '192.0.2.10',
            'test-agent',
            str_repeat('a', 32),
            null,
            $start->modify('+' . $attempt . ' seconds'),
        );
        $assert(!$failure->succeeded, 'Wrong password authenticated.');
    }
    $blocked = $service->login(
        'admin@example.test',
        'correct horse battery staple',
        '192.0.2.10',
        'test-agent',
        str_repeat('b', 32),
        null,
        $start->modify('+10 seconds'),
    );
    $assert(!$blocked->succeeded && $blocked->retryAfterSeconds > 0, 'Throttle did not block a valid password.');
    $ipBlocked = $service->login(
        'different@example.test',
        'another password',
        '192.0.2.10',
        'test-agent',
        str_repeat('b', 32),
        null,
        $start->modify('+10 seconds'),
    );
    $assert(!$ipBlocked->succeeded && $ipBlocked->retryAfterSeconds > 0, 'IP throttle did not span identifiers.');
    $identifierBlocked = $service->login(
        'admin@example.test',
        'correct horse battery staple',
        '192.0.2.11',
        'test-agent',
        str_repeat('b', 32),
        null,
        $start->modify('+10 seconds'),
    );
    $assert(!$identifierBlocked->succeeded && $identifierBlocked->retryAfterSeconds > 0, 'Identifier throttle did not span IP addresses.');

    $loginTime = $start->modify('+1000 seconds');
    $login = $service->login(
        'admin@example.test',
        'correct horse battery staple',
        '192.0.2.10',
        'test-agent',
        str_repeat('c', 32),
        null,
        $loginTime,
    );
    $assert($login->succeeded && $login->session !== null, 'Valid login failed after throttle expiry.');
    $token = $login->session->cookieToken;
    $storedToken = (string) $pdo->query('SELECT token_hash FROM administrator_sessions ORDER BY id DESC LIMIT 1')->fetchColumn();
    $assert($storedToken === hash('sha256', $token) && $storedToken !== $token, 'Plaintext session token was stored.');
    $identity = $service->authenticateSession($token, '192.0.2.10', str_repeat('d', 32), $loginTime->modify('+100 seconds'));
    $assert($identity !== null && $identity->administratorId === $administratorId, 'Valid session did not authenticate.');

    $changed = $service->changePassword(
        $identity,
        'correct horse battery staple',
        'a newly changed secure password',
        'a newly changed secure password',
        '192.0.2.10',
        'test-agent',
        str_repeat('e', 32),
        $loginTime->modify('+200 seconds'),
    );
    $assert($service->authenticateSession($token, '', null, $loginTime->modify('+201 seconds')) === null, 'Old session survived password change.');
    $assert($service->authenticateSession($changed->cookieToken, '', null, $loginTime->modify('+201 seconds')) !== null, 'Replacement session is invalid.');
    $assert(!$service->login('admin@example.test', 'correct horse battery staple', '198.51.100.1', 'agent', null, null, $loginTime->modify('+202 seconds'))->succeeded, 'Old password still authenticated.');
    $assert($service->login('admin@example.test', 'a newly changed secure password', '198.51.100.1', 'agent', null, null, $loginTime->modify('+203 seconds'))->succeeded, 'New password did not authenticate.');

    $idleLogin = $service->login('admin@example.test', 'a newly changed secure password', '203.0.113.2', 'agent', null, null, $loginTime->modify('+300 seconds'));
    $assert($idleLogin->session !== null, 'Idle-expiry fixture login failed.');
    $assert($service->authenticateSession($idleLogin->session->cookieToken, '', null, $loginTime->modify('+901 seconds')) === null, 'Idle-expired session authenticated.');

    $absoluteLogin = $service->login('admin@example.test', 'a newly changed secure password', '203.0.113.3', 'agent', null, null, $loginTime->modify('+300 seconds'));
    $assert($absoluteLogin->session !== null, 'Absolute-expiry fixture login failed.');
    $absoluteToken = $absoluteLogin->session->cookieToken;
    foreach ([800, 1300, 1750] as $offset) {
        $assert($service->authenticateSession($absoluteToken, '', null, $loginTime->modify('+' . $offset . ' seconds')) !== null, 'Active session expired before its absolute limit.');
    }
    $assert($service->authenticateSession($absoluteToken, '', null, $loginTime->modify('+2101 seconds')) === null, 'Absolute-expired session authenticated.');

    $service->resetAdministratorPassword('admin@example.test', 'recovered administrator password', $loginTime->modify('+1000 seconds'));
    $assert($service->authenticateSession($changed->cookieToken, '', null, $loginTime->modify('+1001 seconds')) === null, 'CLI recovery did not revoke sessions.');
    $assert($service->login('admin@example.test', 'recovered administrator password', '198.51.100.3', 'agent', null, null, $loginTime->modify('+1002 seconds'))->succeeded, 'Recovered password did not authenticate.');

    $eventTypes = $pdo->query('SELECT DISTINCT event_type FROM audit_events')->fetchAll(PDO::FETCH_COLUMN);
    $assert(in_array('login_failed', $eventTypes, true) && in_array('password_changed', $eventTypes, true), 'Expected safe audit events are missing.');
    fwrite(STDOUT, sprintf("Authentication database checks passed (%d assertions).\n", $assertions));
} finally {
    $drop($pdo);
}
