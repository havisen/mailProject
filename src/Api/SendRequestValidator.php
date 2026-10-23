<?php

declare(strict_types=1);

namespace App\Api;

use App\Submissions\SubmissionRequest;

final class SendRequestValidator
{
    private const FIELDS = ['account_id', 'to', 'subject', 'text', 'html', 'cc', 'bcc', 'reply_to', 'send_before'];

    public function validate(array $input, ?\DateTimeImmutable $now = null): ValidatedSendRequest
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $unknown = array_diff(array_keys($input), self::FIELDS);
        if ($unknown !== []) {
            throw new ApiException(422, 'unknown_field', 'The request contains an unknown field.');
        }
        foreach (['account_id', 'to', 'subject', 'text'] as $required) {
            if (!array_key_exists($required, $input)) {
                throw new ApiException(422, 'missing_field', 'A required field is missing.');
            }
        }
        $accountId = $this->string($input['account_id'], 'account_id');
        if (preg_match('/\Aacct_[a-f0-9]{32}\z/D', $accountId) !== 1) {
            throw new ApiException(422, 'invalid_account_id', 'account_id is invalid.');
        }
        $subject = trim($this->string($input['subject'], 'subject'));
        $text = $this->string($input['text'], 'text');
        if ($subject === '' || $this->length($subject) > 200 || trim($text) === '') {
            throw new ApiException(422, 'invalid_content', 'subject and text must be nonempty and within limits.');
        }
        $this->rejectControls($subject, 'subject');

        $to = $this->recipients($input['to'], 'to');
        $cc = $this->recipients($input['cc'] ?? [], 'cc');
        $bcc = $this->recipients($input['bcc'] ?? [], 'bcc');
        if ($to === []) {
            throw new ApiException(422, 'invalid_recipients', 'At least one To recipient is required.');
        }
        $all = array_merge($to, $cc, $bcc);
        if (count($all) > 10) {
            throw new ApiException(422, 'too_many_recipients', 'At most 10 total recipients are allowed.');
        }
        $seen = [];
        foreach ($all as $recipient) {
            $key = strtolower($recipient['email']);
            if (isset($seen[$key])) {
                throw new ApiException(422, 'duplicate_recipient', 'Duplicate normalized recipients are not allowed.');
            }
            $seen[$key] = true;
        }

        $html = null;
        if (array_key_exists('html', $input)) {
            $html = $this->string($input['html'], 'html');
        }
        $replyTo = null;
        if (array_key_exists('reply_to', $input)) {
            $replyTo = $this->recipient($input['reply_to'], 'reply_to');
        }
        $deadline = null;
        if (array_key_exists('send_before', $input)) {
            $sendBefore = $this->string($input['send_before'], 'send_before');
            if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})\z/D', $sendBefore) !== 1) {
                throw new ApiException(422, 'invalid_send_before', 'send_before must be RFC3339 with a timezone.');
            }
            try {
                $deadline = new \DateTimeImmutable($sendBefore);
            } catch (\Exception) {
                throw new ApiException(422, 'invalid_send_before', 'send_before is not a valid timestamp.');
            }
            $seconds = $deadline->getTimestamp() - $now->getTimestamp();
            if ($seconds <= 0 || $seconds > 86400) {
                throw new ApiException(422, 'invalid_send_before', 'send_before must be in the future and within 24 hours.');
            }
        }

        $canonical = [
            'account_id' => $accountId,
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
        ];
        foreach (['html' => $html, 'cc' => $cc, 'bcc' => $bcc, 'reply_to' => $replyTo] as $name => $value) {
            if (array_key_exists($name, $input)) {
                $canonical[$name] = $value;
            }
        }
        if (array_key_exists('send_before', $input)) {
            $canonical['send_before'] = $deadline?->format('Y-m-d\TH:i:s.uP');
        }
        $canonicalJson = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new ValidatedSendRequest(
            new SubmissionRequest(
                $accountId,
                'api',
                $to,
                $cc,
                $bcc,
                $subject,
                $text,
                $html,
                $replyTo,
                $deadline
            ),
            $canonicalJson,
            count($all)
        );
    }

    /** @return list<array{email:string,name?:string}> */
    private function recipients(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ApiException(422, 'invalid_type', $field . ' must be an array.');
        }
        return array_map(fn (mixed $item): array => $this->recipient($item, $field), $value);
    }

    /** @return array{email:string,name?:string} */
    private function recipient(mixed $value, string $field): array
    {
        if (!is_array($value) || array_is_list($value) || array_diff(array_keys($value), ['email', 'name']) !== []) {
            throw new ApiException(422, 'invalid_recipient', $field . ' recipients must contain email and optional name.');
        }
        if (!array_key_exists('email', $value)) {
            throw new ApiException(422, 'invalid_recipient', 'Recipient email is required.');
        }
        $email = trim($this->string($value['email'], $field . '.email'));
        $this->rejectControls($email, $field . '.email');
        $at = strrpos($email, '@');
        if ($at === false) {
            throw new ApiException(422, 'invalid_email', 'A recipient email is invalid.');
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        if ($local === '' || preg_match('/[^\x21-\x7E]/', $local) === 1) {
            throw new ApiException(422, 'smtputf8_not_supported', 'SMTPUTF8 local parts are not supported.');
        }
        $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($asciiDomain === false) {
            throw new ApiException(422, 'invalid_email', 'A recipient email domain is invalid.');
        }
        $email = $local . '@' . strtolower($asciiDomain);
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException(422, 'invalid_email', 'A recipient email is invalid.');
        }
        $recipient = ['email' => $email];
        if (array_key_exists('name', $value)) {
            $name = trim($this->string($value['name'], $field . '.name'));
            $this->rejectControls($name, $field . '.name');
            if ($this->length($name) > 120) {
                throw new ApiException(422, 'invalid_name', 'Recipient names may not exceed 120 characters.');
            }
            if ($name !== '') {
                $recipient['name'] = $name;
            }
        }
        return $recipient;
    }

    private function string(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'invalid_type', $field . ' must be a string.');
        }
        return $value;
    }

    private function rejectControls(string $value, string $field): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ApiException(422, 'invalid_characters', $field . ' contains invalid control characters.');
        }
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
