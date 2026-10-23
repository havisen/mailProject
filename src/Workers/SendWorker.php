<?php

declare(strict_types=1);

namespace App\Workers;

use App\Jobs\JobExecutionContext;
use App\Jobs\JobHandler;
use App\Jobs\JobResult;
use App\Mail\DeliveryResult;
use App\Mail\SmtpTransport;
use App\Mail\TransportMessage;
use App\Services\SendingAccountProvider;
use App\Submissions\SubmissionDeliveryStore;

final class SendWorker implements JobHandler
{
    /** @param list<int> $retryDelaysSeconds */
    public function __construct(
        private SubmissionDeliveryStore $submissions,
        private SendingAccountProvider $accounts,
        private SmtpTransport $transport,
        private array $retryDelaysSeconds = [60, 120, 240],
        private int $connectTimeoutSeconds = 10,
        private int $operationTimeoutSeconds = 20
    ) {
    }

    public function handle(array $job, JobExecutionContext $context): JobResult
    {
        $submissionId = (string) ($job['target_reference'] ?? '');
        $accountId = (string) ($job['account_reference'] ?? '');
        $account = $this->accounts->configurationForWorker($accountId);
        if ($account === null) {
            $this->submissions->failQueuedWithoutAttempt($submissionId, 'account_disabled');
            return JobResult::fail('account_disabled');
        }

        $snapshot = $this->submissions->claimForSending($submissionId);
        if ($snapshot === null) {
            return JobResult::complete();
        }
        $meta = $snapshot['_submission'];
        unset($snapshot['_submission']);
        $message = $this->message($snapshot, $meta, $account, $context);
        $result = $this->transport->deliver($message);

        try {
            return $this->persistResult($submissionId, $meta, $result);
        } catch (\Throwable) {
            // SMTP may already have accepted DATA. Leave the lease to expire so
            // recoverExpired() can atomically mark the submission uncertain.
            return JobResult::abandon();
        }
    }

    public function recoverExpired(array $job, JobExecutionContext $context): JobResult
    {
        return $this->submissions->recoverExpiredSend((string) $job['target_reference']);
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed> $meta @param array<string, mixed> $account */
    private function message(
        array $snapshot,
        array $meta,
        array $account,
        JobExecutionContext $context
    ): TransportMessage {
        $recipients = [];
        $ordinal = 0;
        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($snapshot[$type] ?? [] as $recipient) {
                $recipients[] = [
                    'ordinal' => $ordinal++,
                    'type' => $type,
                    'email' => is_array($recipient) ? (string) $recipient['email'] : (string) $recipient,
                    'name' => is_array($recipient) ? (string) ($recipient['name'] ?? '') : '',
                ];
            }
        }
        return new TransportMessage(
            (string) $meta['message_id'],
            (string) $snapshot['from']['email'],
            (string) ($snapshot['from']['name'] ?? ''),
            $snapshot['reply_to'] === null
                ? null
                : (is_array($snapshot['reply_to']) ? (string) $snapshot['reply_to']['email'] : (string) $snapshot['reply_to']),
            (string) $snapshot['subject'],
            (string) $snapshot['text'],
            isset($snapshot['html']) ? (string) $snapshot['html'] : null,
            $recipients,
            [
                'host' => (string) $account['smtp_host'],
                'port' => (int) $account['smtp_port'],
                'tls' => (string) $account['smtp_tls'],
                'username' => (string) $account['smtp_username'],
                'password' => (string) $account['password'],
            ],
            $context->networkTimeoutSeconds($this->connectTimeoutSeconds),
            $context->networkTimeoutSeconds($this->operationTimeoutSeconds)
        );
    }

    /** @param array<string, mixed> $meta */
    private function persistResult(string $submissionId, array $meta, DeliveryResult $result): JobResult
    {
        if (in_array($result->disposition, ['accepted', 'partially_accepted'], true)) {
            $this->submissions->finishAttempt(
                $submissionId,
                $result->disposition,
                $result->failureCategory,
                $result->recipientOutcomes
            );
            return JobResult::complete();
        }
        if ($result->disposition === 'uncertain') {
            $this->submissions->finishAttempt(
                $submissionId,
                'uncertain',
                'uncertain_outcome',
                $result->recipientOutcomes
            );
            return JobResult::fail('uncertain_outcome');
        }

        $attempt = (int) $meta['attempt'];
        $deadline = new \DateTimeImmutable((string) $meta['deadline_at'], new \DateTimeZone('UTC'));
        $delay = $this->retryDelaysSeconds[$attempt - 1] ?? null;
        if ($result->disposition === 'transient' && $delay !== null) {
            $next = new \DateTimeImmutable('+' . $delay . ' seconds', new \DateTimeZone('UTC'));
            if ($next < $deadline && $this->submissions->recordAndRequeueDefinitelyNotAccepted(
                $submissionId,
                $result->failureCategory ?? 'network',
                $result->recipientOutcomes
            )) {
                return JobResult::retry(
                    $result->failureCategory ?? 'network',
                    $next->format('Y-m-d H:i:s.u')
                );
            }
        }

        $this->submissions->finishAttempt(
            $submissionId,
            'failed',
            $result->failureCategory ?? 'permanent',
            $result->recipientOutcomes
        );
        if ($result->authenticationFailure) {
            $this->accounts->recordAuthenticationFailure((string) ($meta['account_id'] ?? ''));
        }
        return JobResult::fail($result->failureCategory ?? 'permanent');
    }
}
