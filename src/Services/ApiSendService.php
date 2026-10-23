<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\ApiException;
use App\Api\ApiPrincipal;
use App\Api\ValidatedSendRequest;
use App\Repositories\ApiAccessRepository;
use PDO;
use PDOException;

final class ApiSendService
{
    private const ENDPOINT = 'POST /api/v1/mail/send';

    public function __construct(
        private PDO $pdo,
        private ApiAccessRepository $access,
        private SubmissionService $submissions,
        private string $fingerprintSecret,
        private int $requestsPerMinute = 30,
        private int $recipientsPerHour = 100,
        private int $retentionSeconds = 604800
    ) {
        if (strlen($fingerprintSecret) < 32) {
            throw new \InvalidArgumentException('The API fingerprint secret must contain at least 32 bytes.');
        }
    }

    public function consumeRequestAllowance(ApiPrincipal $principal, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        try {
            $this->access->consumeRequestLimit($principal->databaseId, $this->requestsPerMinute, $now);
        } catch (ApiException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ApiException(503, 'service_unavailable', 'The request could not be durably processed.', null, $throwable);
        }
    }

    /** @return array{response:array<string,mixed>,replay:bool} */
    public function submit(
        ApiPrincipal $principal,
        ValidatedSendRequest $request,
        string $idempotencyKey,
        string $requestId,
        ?\DateTimeImmutable $now = null
    ): array {
        if (preg_match('/\A[!-~]{1,128}\z/D', $idempotencyKey) !== 1) {
            throw new ApiException(422, 'invalid_idempotency_key', 'Idempotency-Key must be 1-128 printable non-whitespace ASCII characters.');
        }
        if (!$principal->permits('mail:send', $request->submission->accountId)) {
            throw new ApiException(403, 'account_not_allowed', 'The API key may not use this sending account.');
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $identifier = hash_hmac('sha256', self::ENDPOINT . "\0" . $idempotencyKey, $this->fingerprintSecret, true);
        $fingerprint = hash_hmac('sha256', $request->canonicalJson, $this->fingerprintSecret, true);
        $sqlNow = self::sqlTime($now);

        try {
            $this->pdo->beginTransaction();
            if (!$this->access->isKeyActive($principal->databaseId, $principal->publicId)) {
                throw new ApiException(401, 'invalid_api_key', 'A valid Bearer API key is required.');
            }
            $this->access->deleteExpiredIdentifier($principal->databaseId, self::ENDPOINT, $identifier, $sqlNow);
            $existing = $this->access->findReplay($principal->databaseId, self::ENDPOINT, $identifier, $sqlNow);
            if ($existing !== null) {
                $this->pdo->commit();
                return $this->replay($existing, $fingerprint);
            }

            $this->access->consumeRecipientQuota(
                $request->submission->accountId,
                $request->recipientCount,
                $this->recipientsPerHour,
                $now
            );
            $pending = $this->submissions->createInCurrentTransaction($request->submission);
            $response = [
                'data' => [
                    'id' => $pending->submissionId,
                    'status' => 'queued',
                    'send_before' => self::rfc3339($pending->deadlineAt),
                ],
                'request_id' => $requestId,
            ];
            $responseJson = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->access->storeReplay(
                $principal->databaseId,
                self::ENDPOINT,
                $identifier,
                $fingerprint,
                $pending->submissionId,
                $responseJson,
                self::sqlTime($now->modify('+' . $this->retentionSeconds . ' seconds')),
                $sqlNow
            );
            $this->pdo->commit();
            $this->submissions->confirmCommitted($pending);
            return ['response' => $response, 'replay' => false];
        } catch (ApiException $exception) {
            $this->rollback();
            throw $exception;
        } catch (PDOException $exception) {
            $this->rollback();
            if ((string) $exception->getCode() === '23000') {
                try {
                    $existing = $this->access->findReplay($principal->databaseId, self::ENDPOINT, $identifier, $sqlNow);
                    if ($existing !== null) {
                        return $this->replay($existing, $fingerprint);
                    }
                } catch (\Throwable) {
                    // Fall through to the deliberately generic availability error.
                }
            }
            throw new ApiException(503, 'service_unavailable', 'The request could not be durably processed.', null, $exception);
        } catch (\Throwable $throwable) {
            $this->rollback();
            throw new ApiException(503, 'service_unavailable', 'The request could not be durably processed.', null, $throwable);
        }
    }

    /** @param array<string, mixed> $stored @return array{response:array<string,mixed>,replay:bool} */
    private function replay(array $stored, string $fingerprint): array
    {
        if (!hash_equals((string) $stored['payload_fingerprint'], $fingerprint)) {
            throw new ApiException(409, 'idempotency_conflict', 'The Idempotency-Key was already used with different content.');
        }
        $response = json_decode((string) $stored['response_json'], true, 16, JSON_THROW_ON_ERROR);
        return ['response' => $response, 'replay' => true];
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function sqlTime(\DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function rfc3339(string $sqlTime): string
    {
        return (new \DateTimeImmutable($sqlTime, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }
}
