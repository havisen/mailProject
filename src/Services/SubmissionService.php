<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\JobResult;
use App\Repositories\AccountRepository;
use App\Repositories\JobRepository;
use App\Repositories\SubmissionRepository;
use App\Security\QueuedContentCipher;
use App\Submissions\PendingSubmission;
use App\Submissions\SubmissionRequest;
use App\Submissions\SubmissionResult;
use App\Submissions\SubmissionValidator;
use PDO;
use PDOException;

final class SubmissionService
{
    public function __construct(
        private PDO $pdo,
        private AccountRepository $accounts,
        private SubmissionRepository $submissions,
        private JobRepository $jobs,
        private QueuedContentCipher $cipher,
        private SubmissionValidator $validator
    ) {
    }

    /** Creates and commits a submission and its job before returning acceptance. */
    public function create(SubmissionRequest $request): SubmissionResult
    {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('Use createInCurrentTransaction() inside a caller transaction.');
        }
        $this->pdo->beginTransaction();
        try {
            $pending = $this->createInCurrentTransaction($request);
            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
        return $this->confirmCommitted($pending);
    }

    /**
     * API idempotency may call this after opening its own transaction. The
     * returned object is provisional and must not be exposed as acceptance.
     */
    public function createInCurrentTransaction(SubmissionRequest $request): PendingSubmission
    {
        if (!$this->pdo->inTransaction()) {
            throw new \LogicException('A caller transaction is required.');
        }
        $account = $this->accounts->findByPublicId($request->accountId);
        if ($account === null) {
            throw new \DomainException('Sending account not found.');
        }

        $submissionId = 'sub_' . bin2hex(random_bytes(16));
        $validated = $this->validator->validate($request, $account, $submissionId);
        $operationId = $request->browserOperationId;
        if ($operationId !== null) {
            $existing = $this->submissions->findByBrowserOperation(
                (int) $account['id'],
                $request->source,
                $operationId
            );
            if ($existing !== null) {
                return $this->existingPending($existing, $validated['snapshot_hash']);
            }
        }

        $snapshotJson = json_encode(
            $validated['snapshot'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $snapshotCiphertext = $this->cipher->encryptSnapshot($submissionId, $snapshotJson);
        $storedRecipients = [];
        foreach ($validated['recipients'] as $ordinal => $recipient) {
            $ciphertext = $this->cipher->encryptRecipient($submissionId, $ordinal, $recipient['address']);
            $storedRecipients[] = [
                'type' => $recipient['type'],
                'ordinal' => $ordinal,
                'ciphertext' => $ciphertext,
                'address_hash' => hash('sha256', $ciphertext),
            ];
        }
        $now = self::now();

        try {
            $this->submissions->insert([
                'public_id' => $submissionId,
                'account_id' => (int) $account['id'],
                'account_reference' => $account['public_id'],
                'source' => $request->source,
                'browser_operation_id' => $operationId,
                'message_id' => $validated['message_id'],
                'snapshot_ciphertext' => $snapshotCiphertext,
                // Hash ciphertext, never the guessable private plaintext snapshot.
                'snapshot_hash' => hash('sha256', $snapshotCiphertext),
                'deadline_at' => $validated['deadline_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $storedRecipients);
        } catch (PDOException $exception) {
            if ($operationId === null || (string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing = $this->submissions->findByBrowserOperation(
                (int) $account['id'],
                $request->source,
                $operationId
            );
            if ($existing === null) {
                throw $exception;
            }
            return $this->existingPending($existing, $validated['snapshot_hash']);
        }

        $this->jobs->enqueue([
            'type' => 'smtp.send',
            'queue_name' => 'outbound',
            'target_type' => 'submission',
            'target_reference' => $submissionId,
            'account_reference' => $account['public_id'],
            'dedupe_key' => $submissionId,
            'priority' => 10,
            'scheduled_at' => $now,
            'max_attempts' => 3,
        ]);

        return new PendingSubmission(
            $submissionId,
            $validated['deadline_at'],
            $validated['message_id'],
            false
        );
    }

    /** Confirms persistence after a caller-owned transaction commits. */
    public function confirmCommitted(PendingSubmission $pending): SubmissionResult
    {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('Submission acceptance cannot be returned before commit.');
        }
        $stored = $this->submissions->findMetadata($pending->submissionId);
        if ($stored === null) {
            throw new \RuntimeException('Committed submission could not be confirmed.');
        }
        return new SubmissionResult(
            (string) $stored['public_id'],
            (string) $stored['state'],
            (string) $stored['deadline_at'],
            (string) $stored['message_id'],
            $pending->duplicate
        );
    }

    /** @return array<string, mixed> Safe only behind administrator authentication. */
    public function detailForAuthenticatedUi(string $submissionId): array
    {
        $row = $this->submissions->findEncryptedDetail($submissionId);
        if ($row === null) {
            throw new \DomainException('Submission not found.');
        }
        $json = $this->cipher->decryptSnapshot($submissionId, (string) $row['snapshot_ciphertext']);
        unset($row['snapshot_ciphertext']);
        $row['snapshot'] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $row['recipient_outcomes'] = $this->submissions->listRecipientOutcomes($submissionId);
        return $row;
    }

    /** @return array<string, mixed> Safe metadata for an authenticated Outbox view. */
    public function statusForAuthenticatedUi(string $submissionId): array
    {
        $row = $this->submissions->findMetadata($submissionId);
        if ($row === null) {
            throw new \DomainException('Submission not found.');
        }
        return $row;
    }

    /** Atomically cancels only submissions that are still queued. */
    public function cancelQueued(string $submissionId): bool
    {
        $this->beginOwnedTransaction();
        try {
            $now = self::now();
            $canceled = $this->submissions->cancelQueued($submissionId, $now);
            if ($canceled) {
                $this->jobs->cancelQueuedForTarget('submission', $submissionId);
            }
            $this->pdo->commit();
            return $canceled;
        } catch (\Throwable $throwable) {
            $this->rollback();
            throw $throwable;
        }
    }

    /** @return array<string, mixed>|null Immutable content for the transport worker. */
    public function claimForSending(string $submissionId, ?\DateTimeImmutable $startedAt = null): ?array
    {
        $this->beginOwnedTransaction();
        try {
            $row = $this->submissions->lockByPublicId($submissionId);
            if ($row === null || $row['state'] !== 'queued') {
                $this->pdo->commit();
                return null;
            }
            $startedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $now = $startedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            if ((string) $row['deadline_at'] <= $now) {
                $this->submissions->markExpired($submissionId, $now);
                $this->pdo->commit();
                return null;
            }
            $ciphertext = (string) $row['snapshot_ciphertext'];
            $json = $this->cipher->decryptSnapshot($submissionId, $ciphertext);
            $snapshot = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!$this->submissions->markSending($submissionId, $now)) {
                throw new \RuntimeException('Submission claim transition was lost.');
            }
            $this->pdo->commit();
            return $snapshot;
        } catch (\Throwable $throwable) {
            $this->rollback();
            throw $throwable;
        }
    }

    /**
     * @param list<array{ordinal:int,outcome:string,failure_category:?string}> $outcomes
     */
    public function finishAttempt(
        string $submissionId,
        string $state,
        ?string $failureCategory,
        array $outcomes
    ): void {
        $failureCategory = $failureCategory === null ? null : $this->safeCategory($failureCategory);
        $this->beginOwnedTransaction();
        try {
            $row = $this->submissions->lockByPublicId($submissionId);
            if ($row === null || $row['state'] !== 'sending') {
                throw new \DomainException('Submission is not in a sendable attempt.');
            }
            $storedOutcomes = [];
            $seenOrdinals = [];
            $accepted = 0;
            foreach ($outcomes as $outcome) {
                $ordinal = (int) $outcome['ordinal'];
                if ($ordinal < 0 || isset($seenOrdinals[$ordinal])) {
                    throw new \InvalidArgumentException('Recipient outcome ordinals must be unique and nonnegative.');
                }
                $seenOrdinals[$ordinal] = true;
                $accepted += $outcome['outcome'] === 'accepted' ? 1 : 0;
                $storedOutcomes[] = [
                    'ordinal' => $ordinal,
                    'outcome' => $outcome['outcome'],
                    'failure_category' => $outcome['failure_category'] === null
                        ? null
                        : $this->safeCategory($outcome['failure_category']),
                ];
            }
            $recipientCount = $this->submissions->recipientCount((int) $row['id']);
            $allRecipientsCovered = count($storedOutcomes) === $recipientCount;
            for ($ordinal = 0; $ordinal < $recipientCount; $ordinal++) {
                $allRecipientsCovered = $allRecipientsCovered && isset($seenOrdinals[$ordinal]);
            }
            if (!$allRecipientsCovered) {
                throw new \InvalidArgumentException('Every immutable recipient needs exactly one attempt outcome.');
            }
            if (($state === 'accepted' && $accepted !== $recipientCount)
                || ($state === 'partially_accepted' && ($accepted === 0 || $accepted === $recipientCount))
                || ($state === 'failed' && $accepted !== 0)) {
                throw new \InvalidArgumentException('Recipient outcomes do not match the submission terminal state.');
            }
            $now = self::now();
            $this->submissions->recordRecipientOutcomes(
                (int) $row['id'],
                (int) $row['attempts'],
                $storedOutcomes,
                $now
            );
            if (!$this->submissions->finishSending($submissionId, $state, $failureCategory, $now)) {
                throw new \RuntimeException('Submission terminal transition was lost.');
            }
            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->rollback();
            throw $throwable;
        }
    }

    /** Safe retry is allowed only when SMTP acceptance is definitely impossible. */
    public function requeueDefinitelyNotAccepted(
        string $submissionId,
        string $failureCategory,
        bool $smtpAcceptancePossible
    ): bool {
        if ($smtpAcceptancePossible) {
            throw new \LogicException('A possibly accepted SMTP attempt must never be requeued.');
        }
        $failureCategory = $this->safeCategory($failureCategory);
        $this->beginOwnedTransaction();
        try {
            $changed = $this->submissions->requeueSending($submissionId, $failureCategory, self::now());
            $this->pdo->commit();
            return $changed;
        } catch (\Throwable $throwable) {
            $this->rollback();
            throw $throwable;
        }
    }

    /** Decision used by the future SMTP handler's expired-lease callback. */
    public function recoverExpiredSend(string $submissionId): JobResult
    {
        $this->beginOwnedTransaction();
        try {
            $row = $this->submissions->lockByPublicId($submissionId);
            if ($row === null) {
                $this->pdo->commit();
                return JobResult::fail('permanent');
            }
            if ($row['state'] === 'sending') {
                $this->submissions->finishSending($submissionId, 'uncertain', 'uncertain_outcome', self::now());
                $this->pdo->commit();
                return JobResult::fail('uncertain_outcome');
            }
            if ($row['state'] === 'queued') {
                $this->pdo->commit();
                return JobResult::retry('internal', gmdate('Y-m-d H:i:s.u', time() + 5));
            }
            $this->pdo->commit();
            return JobResult::complete();
        } catch (\Throwable $throwable) {
            $this->rollback();
            throw $throwable;
        }
    }

    /** @param array<string, mixed> $existing */
    private function existingPending(array $existing, string $snapshotHash): PendingSubmission
    {
        $existingJson = $this->cipher->decryptSnapshot(
            (string) $existing['public_id'],
            (string) $existing['snapshot_ciphertext']
        );
        if (!hash_equals(hash('sha256', $existingJson), $snapshotHash)) {
            throw new \DomainException('Browser operation ID was already used for different content.');
        }
        return new PendingSubmission(
            (string) $existing['public_id'],
            (string) $existing['deadline_at'],
            (string) $existing['message_id'],
            true
        );
    }

    private function beginOwnedTransaction(): void
    {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('This operation owns its transaction.');
        }
        $this->pdo->beginTransaction();
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function safeCategory(string $category): string
    {
        return in_array($category, JobRepository::FAILURE_CATEGORIES, true) ? $category : 'internal';
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
