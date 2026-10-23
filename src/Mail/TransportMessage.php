<?php

declare(strict_types=1);

namespace App\Mail;

final class TransportMessage
{
    /**
     * @param list<array{ordinal:int,type:string,email:string,name:string}> $recipients
     * @param array{host:string,port:int,tls:string,username:string,password:string} $smtp
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly ?string $replyTo,
        public readonly string $subject,
        public readonly string $text,
        public readonly ?string $html,
        public readonly array $recipients,
        public readonly array $smtp,
        public readonly int $connectTimeoutSeconds,
        public readonly int $operationTimeoutSeconds
    ) {
    }
}
