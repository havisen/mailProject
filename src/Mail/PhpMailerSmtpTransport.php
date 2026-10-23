<?php

declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;

final class PhpMailerSmtpTransport implements SmtpTransport
{
    public function deliver(TransportMessage $message): DeliveryResult
    {
        $smtp = new InstrumentedSmtp();
        $mailer = new PHPMailer(true);
        $mailer->setSMTPInstance($smtp);
        $mailer->isSMTP();
        $mailer->Host = $message->smtp['host'];
        $mailer->Port = $message->smtp['port'];
        $mailer->SMTPSecure = $message->smtp['tls'] === 'implicit'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAutoTLS = true;
        $mailer->SMTPAuth = true;
        $mailer->Username = $message->smtp['username'];
        $mailer->Password = $message->smtp['password'];
        $mailer->Timeout = max(1, $message->connectTimeoutSeconds);
        $smtp->Timelimit = max(1, $message->operationTimeoutSeconds);
        $mailer->SMTPDebug = 0;
        $mailer->Debugoutput = static function (string $message, int $level): void {
            // SMTP transcripts can contain private/provider data and are discarded.
        };
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mailer->MessageID = $message->messageId;
        $mailer->XMailer = null;
        $mailer->setFrom($message->fromEmail, $message->fromName, false);
        if ($message->replyTo !== null) {
            $mailer->addReplyTo($message->replyTo);
        }
        foreach ($message->recipients as $recipient) {
            match ($recipient['type']) {
                'to' => $mailer->addAddress($recipient['email'], $recipient['name']),
                'cc' => $mailer->addCC($recipient['email'], $recipient['name']),
                'bcc' => $mailer->addBCC($recipient['email'], $recipient['name']),
                default => throw new \InvalidArgumentException('Unknown recipient type.'),
            };
        }
        $mailer->Subject = $message->subject;
        if ($message->html !== null && $message->html !== '') {
            $mailer->isHTML(true);
            $mailer->Body = $message->html;
            $mailer->AltBody = $message->text;
        } else {
            $mailer->isHTML(false);
            $mailer->Body = $message->text;
        }

        $exception = null;
        try {
            $mailer->send();
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        } finally {
            $mailer->smtpClose();
            // Remove the password from the long-lived object as soon as possible.
            $mailer->Password = '';
        }

        return $this->classify($message, $smtp, $exception);
    }

    private function classify(
        TransportMessage $message,
        InstrumentedSmtp $smtp,
        ?\Throwable $exception
    ): DeliveryResult {
        $records = $smtp->recipientResults();
        $acceptedCount = 0;
        $outcomes = [];
        foreach ($message->recipients as $recipient) {
            $record = $records[strtolower($recipient['email'])] ?? null;
            if ($record !== null && $record['accepted']) {
                $acceptedCount++;
                if ($smtp->dataAccepted()) {
                    $outcome = 'accepted';
                    $category = null;
                } elseif ($smtp->dataConclusiveFailure()) {
                    $outcome = $this->temporaryCode($smtp->dataCode()) ? 'transient_failure' : 'rejected';
                    $category = $this->temporaryCode($smtp->dataCode()) ? 'network' : 'permanent';
                } else {
                    $outcome = 'unknown';
                    $category = 'uncertain_outcome';
                }
            } elseif ($record !== null) {
                $outcome = $this->temporaryCode($record['code']) ? 'transient_failure' : 'rejected';
                $category = $this->temporaryCode($record['code']) ? 'network' : 'permanent';
            } else {
                $outcome = 'transient_failure';
                $category = $this->preDataCategory($exception, $smtp->safeLastCode());
            }
            $outcomes[] = [
                'ordinal' => $recipient['ordinal'],
                'outcome' => $outcome,
                'failure_category' => $category,
            ];
        }

        if ($smtp->dataAccepted()) {
            $disposition = $acceptedCount === count($message->recipients) ? 'accepted' : 'partially_accepted';
            return new DeliveryResult($disposition, $disposition === 'accepted' ? null : 'permanent', $outcomes);
        }
        if ($smtp->dataStarted() && !$smtp->dataConclusiveFailure()) {
            return new DeliveryResult('uncertain', 'uncertain_outcome', $outcomes);
        }
        if ($smtp->dataConclusiveFailure()) {
            $temporary = $this->temporaryCode($smtp->dataCode());
            return new DeliveryResult($temporary ? 'transient' : 'permanent', $temporary ? 'network' : 'permanent', $outcomes);
        }

        $category = $this->preDataCategory($exception, $smtp->safeLastCode());
        $auth = $category === 'authentication';
        $hasPermanentRecipient = array_any(
            $outcomes,
            static fn (array $outcome): bool => $outcome['outcome'] === 'rejected'
        );
        $permanent = $auth || in_array($category, ['configuration', 'tls', 'permanent'], true) || $hasPermanentRecipient;
        return new DeliveryResult($permanent ? 'permanent' : 'transient', $category, $outcomes, $auth);
    }

    private function preDataCategory(?\Throwable $exception, int $smtpCode): string
    {
        if (in_array($smtpCode, [530, 534, 535, 538], true)) {
            return 'authentication';
        }
        if ($smtpCode >= 500) {
            return 'permanent';
        }
        $message = strtolower($exception?->getMessage() ?? '');
        if (str_contains($message, 'authenticate')) {
            return 'authentication';
        }
        if (str_contains($message, 'certificate') || str_contains($message, 'crypto') || str_contains($message, 'tls')) {
            return 'tls';
        }
        return 'network';
    }

    private function temporaryCode(int $code): bool
    {
        return $code >= 400 && $code <= 499;
    }
}
