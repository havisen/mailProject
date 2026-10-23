<?php

declare(strict_types=1);

use App\Security\CsrfTokenManager;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\SecureCookieManager;

$root = dirname(__DIR__, 2);
require $root . '/src/Security/PasswordPolicy.php';
require $root . '/src/Security/PasswordHasher.php';
require $root . '/src/Security/CsrfTokenManager.php';
require $root . '/src/Security/SecureCookieManager.php';

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
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
};

$policy = new PasswordPolicy(14, 1024);
$policy->validate('correct horse battery staple');
$assertThrows(static fn () => $policy->validate('too short'), 'Short passwords must be rejected.');
$assertThrows(static fn () => $policy->validate("valid length but\nnewline"), 'Password control characters must be rejected.');
$assertThrows(static fn () => $policy->validate(str_repeat('x', 1025)), 'Oversized passwords must be rejected.');
$assertThrows(static fn () => $policy->validate(str_repeat('x', 14) . "\xC3\x28"), 'Invalid UTF-8 passwords must be rejected.');

$hasher = new PasswordHasher();
$hash = $hasher->hash('correct horse battery staple');
$assert(str_starts_with($hash, '$argon2id$'), 'Passwords must use Argon2id.');
$assert($hasher->verify('correct horse battery staple', $hash), 'Valid password did not verify.');
$assert(!$hasher->verify('wrong password', $hash), 'Wrong password verified.');

$csrf = new CsrfTokenManager(str_repeat('s', 32), 300);
$binding = $csrf->newBindingSecret();
$token = $csrf->issue('password-change', $binding, 1_000);
$assert($csrf->validate($token, 'password-change', $binding, 1_100), 'Valid CSRF token failed.');
$assert(!$csrf->validate($token, 'logout', $binding, 1_100), 'CSRF token was not action-bound.');
$assert(!$csrf->validate($token, 'password-change', str_repeat('0', 64), 1_100), 'CSRF token was not session-bound.');
$assert(!$csrf->validate($token, 'password-change', $binding, 1_301), 'Expired CSRF token verified.');
$assert(!$csrf->validate('not-a-token', 'password-change', $binding, 1_100), 'Malformed CSRF token verified.');

$cookies = new SecureCookieManager('mailpanel_admin', 'mailpanel_login_csrf', 300);
$sessionCookie = $cookies->session('opaque-token', 2_000, 1_000);
$assert(str_contains($sessionCookie, 'Secure'), 'Session cookie is not Secure.');
$assert(str_contains($sessionCookie, 'HttpOnly'), 'Session cookie is not HttpOnly.');
$assert(str_contains($sessionCookie, 'SameSite=Strict'), 'Session cookie is not SameSite Strict.');
$assert(str_contains($cookies->clearSession(), 'Max-Age=0'), 'Session clearing cookie is invalid.');

fwrite(STDOUT, sprintf("Authentication unit checks passed (%d assertions).\n", $assertions));
