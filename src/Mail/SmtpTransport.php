<?php

declare(strict_types=1);

namespace App\Mail;

interface SmtpTransport
{
    public function deliver(TransportMessage $message): DeliveryResult;
}
