<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AdminSession;
use App\Http\RequestMetadata;
use App\Middleware\AuthMiddleware;
use App\Security\CsrfTokenManager;
use App\Security\SecureCookieManager;
use App\Services\AuthService;
use DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class AuthController
{
    public function __construct(
        private AuthService $auth,
        private CsrfTokenManager $csrf,
        private SecureCookieManager $cookies,
        private Environment $view,
    ) {
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sessionToken = $request->getCookieParams()[$this->cookies->sessionName()] ?? null;
        if (is_string($sessionToken) && $this->auth->authenticateSession(
            $sessionToken,
            RequestMetadata::clientIp($request),
            RequestMetadata::requestId($request),
        ) instanceof AdminSession) {
            return $response->withStatus(303)->withHeader('Location', '/');
        }

        $response = $this->loginForm($request, $response, null, '', 200);
        return is_string($sessionToken) && $sessionToken !== ''
            ? $response->withAddedHeader('Set-Cookie', $this->cookies->clearSession())
            : $response;
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $challenge = $request->getCookieParams()[$this->cookies->loginCsrfName()] ?? '';
        $csrf = is_string($body['_csrf'] ?? null) ? $body['_csrf'] : '';
        if (!is_string($challenge) || !$this->csrf->validate($csrf, 'login', $challenge)) {
            return $this->loginForm($request, $response, 'Request could not be verified.', '', 403);
        }

        $email = is_string($body['email'] ?? null) ? substr($body['email'], 0, 512) : '';
        $password = is_string($body['password'] ?? null) && strlen($body['password']) <= 1024
            ? $body['password']
            : '';
        $existingToken = $request->getCookieParams()[$this->cookies->sessionName()] ?? null;
        $result = $this->auth->login(
            $email,
            $password,
            RequestMetadata::clientIp($request),
            RequestMetadata::userAgent($request),
            RequestMetadata::requestId($request),
            is_string($existingToken) ? $existingToken : null,
        );
        if (!$result->succeeded || $result->session === null) {
            $status = $result->retryAfterSeconds > 0 ? 429 : 401;
            $response = $this->loginForm(
                $request,
                $response,
                $result->retryAfterSeconds > 0
                    ? 'Sign-in is temporarily unavailable. Try again later.'
                    : 'Email or password is incorrect.',
                $email,
                $status,
            );
            return $result->retryAfterSeconds > 0
                ? $response->withHeader('Retry-After', (string) $result->retryAfterSeconds)
                : $response;
        }

        return $response
            ->withStatus(303)
            ->withHeader('Location', '/')
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', $this->cookies->session(
                $result->session->cookieToken,
                $result->session->identity->absoluteExpiry,
            ))
            ->withAddedHeader('Set-Cookie', $this->cookies->clearLoginCsrf());
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = $this->requiredSession($request);
        $query = $request->getQueryParams();
        return $this->html($response, 'dashboard.twig', [
            'title' => 'Mail panel',
            'administrator' => $session,
            'logout_csrf' => $this->csrf->issue('logout', $session->csrfSecret),
            'notice' => ($query['password'] ?? null) === 'changed' ? 'Password changed. Other sessions were revoked.' : null,
        ]);
    }

    public function showPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->passwordForm($request, $response, null, 200);
    }

    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = $this->requiredSession($request);
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $current = is_string($body['current_password'] ?? null) ? $body['current_password'] : '';
        $new = is_string($body['new_password'] ?? null) ? $body['new_password'] : '';
        $confirmation = is_string($body['new_password_confirmation'] ?? null) ? $body['new_password_confirmation'] : '';
        if (strlen($current) > 1024 || strlen($new) > 1024 || strlen($confirmation) > 1024) {
            return $this->passwordForm($request, $response, 'Password input is too long.', 422);
        }

        try {
            $issued = $this->auth->changePassword(
                $session,
                $current,
                $new,
                $confirmation,
                RequestMetadata::clientIp($request),
                RequestMetadata::userAgent($request),
                RequestMetadata::requestId($request),
            );
        } catch (DomainException|\InvalidArgumentException $exception) {
            return $this->passwordForm($request, $response, $exception->getMessage(), 422);
        }

        return $response
            ->withStatus(303)
            ->withHeader('Location', '/?password=changed')
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', $this->cookies->session(
                $issued->cookieToken,
                $issued->identity->absoluteExpiry,
            ));
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = $this->requiredSession($request);
        $this->auth->logout(
            $session,
            RequestMetadata::clientIp($request),
            RequestMetadata::requestId($request),
        );

        return $response
            ->withStatus(303)
            ->withHeader('Location', '/login?logged_out=1')
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', $this->cookies->clearSession());
    }

    private function loginForm(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?string $error,
        string $email,
        int $status,
    ): ResponseInterface {
        $binding = $this->csrf->newBindingSecret();
        $query = $request->getQueryParams();
        $response = $this->html($response->withStatus($status), 'auth/login.twig', [
            'title' => 'Administrator sign in',
            'csrf_token' => $this->csrf->issue('login', $binding),
            'email' => $email,
            'error' => $error,
            'notice' => ($query['logged_out'] ?? null) === '1' ? 'You have signed out.' : null,
        ]);
        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', $this->cookies->loginCsrf($binding));
    }

    private function passwordForm(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?string $error,
        int $status,
    ): ResponseInterface {
        $session = $this->requiredSession($request);
        return $this->html($response->withStatus($status), 'settings/password.twig', [
            'title' => 'Change password',
            'administrator' => $session,
            'csrf_token' => $this->csrf->issue('password-change', $session->csrfSecret),
            'error' => $error,
        ]);
    }

    private function requiredSession(ServerRequestInterface $request): AdminSession
    {
        $session = $request->getAttribute(AuthMiddleware::ATTRIBUTE);
        if (!$session instanceof AdminSession) {
            throw new \LogicException('Protected route has no administrator session.');
        }
        return $session;
    }

    /** @param array<string, mixed> $data */
    private function html(ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $response->getBody()->write($this->view->render($template, $data));
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}
