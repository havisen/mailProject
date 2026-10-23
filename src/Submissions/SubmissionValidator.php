<?php

declare(strict_types=1);

namespace App\Submissions;

final class SubmissionValidator
{
    public function __construct(
        private int $defaultDeadlineSeconds = 300,
        private int $maximumDeadlineSeconds = 86400,
        private string $messageIdDomain = 'localhost.invalid'
    ) {
        if (filter_var($messageIdDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new \InvalidArgumentException('Invalid Message-ID domain.');
        }
    }

    /**
     * @param array<string, mixed> $account
     * @return array{snapshot:array<string,mixed>,deadline_at:string,snapshot_hash:string,message_id:string,recipients:list<array{type:string,address:string}>}
     */
    public function validate(
        SubmissionRequest $request,
        array $account,
        string $submissionId,
        ?\DateTimeImmutable $now = null
    ): array {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if (!in_array($request->source, ['panel', 'api', 'test'], true)) {
            throw new \InvalidArgumentException('Invalid submission source.');
        }
        if (($account['public_id'] ?? null) !== $request->accountId) {
            throw new \InvalidArgumentException('Submission account does not match the approved account.');
        }
        if (empty($account['enabled'])) {
            throw new \DomainException('The sending account is disabled.');
        }

        $from = $this->email((string) ($account['from_email'] ?? ''), 'approved sender');
        if ($request->requestedFrom !== null && $this->email($request->requestedFrom, 'requested sender') !== $from) {
            throw new \DomainException('The requested sender identity is not approved for this account.');
        }
        $fromName = trim((string) ($account['from_name'] ?? ''));
        $this->withoutNewlines($fromName, 'sender name');

        $subject = trim($request->subject);
        $this->withoutNewlines($subject, 'subject');
        if ($subject === '' || $this->length($subject) > 200) {
            throw new \InvalidArgumentException('Subject must contain 1 to 200 characters.');
        }
        if ($request->text === '' && ($request->html === null || $request->html === '')) {
            throw new \InvalidArgumentException('A text or HTML body is required.');
        }

        $recipients = [];
        $normalized = ['to' => [], 'cc' => [], 'bcc' => []];
        foreach (['to' => $request->to, 'cc' => $request->cc, 'bcc' => $request->bcc] as $type => $addresses) {
            foreach ($addresses as $address) {
                $valid = $this->recipient($address, $type . ' recipient');
                $key = strtolower($valid['email']);
                if (isset($normalized['to'][$key]) || isset($normalized['cc'][$key]) || isset($normalized['bcc'][$key])) {
                    continue;
                }
                $normalized[$type][$key] = is_array($address) ? $valid : $valid['email'];
                $recipients[] = ['type' => $type, 'address' => $valid['email']];
            }
        }
        $recipientLimit = max(1, (int) ($account['max_recipients'] ?? 10));
        if ($recipients === [] || count($recipients) > $recipientLimit) {
            throw new \InvalidArgumentException('Recipient count is outside the account limit.');
        }

        $replyTo = $request->replyTo === null ? ($account['reply_to_email'] ?? null) : $request->replyTo;
        if ($replyTo !== null && $replyTo !== '') {
            $replyToValue = $this->recipient($replyTo, 'Reply-To');
            $replyTo = is_array($replyTo) ? $replyToValue : $replyToValue['email'];
        } else {
            $replyTo = null;
        }

        if ($request->source === 'api' && $request->ownedBlobReferences !== []) {
            throw new \InvalidArgumentException('API submissions do not accept attachments.');
        }
        foreach ($request->ownedBlobReferences as $reference) {
            if (preg_match('/^blob_[a-f0-9]{32}$/', $reference) !== 1) {
                throw new \InvalidArgumentException('Attachments must use owned blob references.');
            }
        }

        if ($request->browserOperationId !== null) {
            if ($request->source !== 'panel'
                || preg_match('/^[A-Za-z0-9_-]{16,128}$/', $request->browserOperationId) !== 1) {
                throw new \InvalidArgumentException('Invalid browser operation ID.');
            }
        }

        $deadline = $request->deadline ?? $now->modify('+' . $this->defaultDeadlineSeconds . ' seconds');
        $seconds = $deadline->getTimestamp() - $now->getTimestamp();
        if ($seconds <= 0 || $seconds > $this->maximumDeadlineSeconds) {
            throw new \InvalidArgumentException('Submission deadline is outside the allowed window.');
        }

        $recipientGroups = [];
        foreach ($normalized as $type => $addresses) {
            $recipientGroups[$type] = array_values($addresses);
        }
        $snapshot = [
            'version' => 1,
            'account_id' => $request->accountId,
            'source' => $request->source,
            'from' => ['email' => $from, 'name' => $fromName],
            'reply_to' => $replyTo,
            'to' => $recipientGroups['to'],
            'cc' => $recipientGroups['cc'],
            'bcc' => $recipientGroups['bcc'],
            'subject' => $subject,
            'text' => $request->text,
            'html' => $request->html,
            'owned_blob_references' => array_values($request->ownedBlobReferences),
        ];
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > (int) ($account['max_outbound_bytes'] ?? 20971520)) {
            throw new \LengthException('Submission content exceeds the account size limit.');
        }

        return [
            'snapshot' => $snapshot,
            'deadline_at' => $deadline->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'snapshot_hash' => hash('sha256', $json),
            'message_id' => '<' . substr($submissionId, 4) . '@' . $this->messageIdDomain . '>',
            'recipients' => $recipients,
        ];
    }

    private function email(string $value, string $label): string
    {
        $value = trim($value);
        $this->withoutNewlines($value, $label);
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid ' . $label . ' address.');
        }
        return $value;
    }

    /** @return array{email:string,name?:string} */
    private function recipient(mixed $value, string $label): array
    {
        if (is_string($value)) {
            return ['email' => $this->email($value, $label)];
        }
        if (!is_array($value) || !isset($value['email']) || !is_string($value['email'])) {
            throw new \InvalidArgumentException('Invalid ' . $label . '.');
        }
        $recipient = ['email' => $this->email($value['email'], $label)];
        if (isset($value['name'])) {
            if (!is_string($value['name'])) {
                throw new \InvalidArgumentException('Invalid ' . $label . ' name.');
            }
            $name = trim($value['name']);
            $this->withoutNewlines($name, $label . ' name');
            if ($this->length($name) > 120) {
                throw new \InvalidArgumentException('Recipient name exceeds 120 characters.');
            }
            if ($name !== '') {
                $recipient['name'] = $name;
            }
        }
        return $recipient;
    }

    private function withoutNewlines(string $value, string $label): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Invalid characters in ' . $label . '.');
        }
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
