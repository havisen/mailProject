<?php

declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\SMTP;

/** Captures command outcomes without retaining SMTP reply text. */
final class InstrumentedSmtp extends SMTP
{
    /** @var array<string, array{accepted:bool,code:int}> */
    private array $recipientResults = [];
    private bool $dataStarted = false;
    private bool $dataAccepted = false;
    private bool $dataConclusiveFailure = false;
    private int $dataCode = 0;

    public function recipient($address, $dsn = '')
    {
        $accepted = parent::recipient($address, $dsn);
        $error = $this->getError();
        $this->recipientResults[strtolower((string) $address)] = [
            'accepted' => $accepted,
            'code' => (int) ($error['smtp_code'] ?? 0),
        ];
        return $accepted;
    }

    public function data($msg_data)
    {
        $this->dataStarted = true;
        try {
            $accepted = parent::data($msg_data);
        } catch (\Throwable $throwable) {
            $this->dataConclusiveFailure = false;
            throw $throwable;
        }
        $error = $this->getError();
        $this->dataCode = (int) ($error['smtp_code'] ?? 0);
        $this->dataAccepted = $accepted;
        $this->dataConclusiveFailure = !$accepted && $this->dataCode >= 400 && $this->dataCode <= 599;
        return $accepted;
    }

    /** @return array<string, array{accepted:bool,code:int}> */
    public function recipientResults(): array
    {
        return $this->recipientResults;
    }

    public function dataStarted(): bool
    {
        return $this->dataStarted;
    }

    public function dataAccepted(): bool
    {
        return $this->dataAccepted;
    }

    public function dataConclusiveFailure(): bool
    {
        return $this->dataConclusiveFailure;
    }

    public function dataCode(): int
    {
        return $this->dataCode;
    }

    public function safeLastCode(): int
    {
        $error = $this->getError();
        return (int) ($error['smtp_code'] ?? 0);
    }
}
