<?php

declare(strict_types=1);

namespace App\Submissions;

final class SubmissionResult
{
    public function __construct(
        public readonly string $submissionId,
        public readonly string $state,
        public readonly string $deadlineAt,
        public readonly string $messageId,
        public readonly bool $duplicate
    ) {
    }

    /** @return array{submission_id:string,state:string,deadline:string,message_id:string,duplicate:bool} */
    public function toArray(): array
    {
        return [
            'submission_id' => $this->submissionId,
            'state' => $this->state,
            'deadline' => $this->deadlineAt,
            'message_id' => $this->messageId,
            'duplicate' => $this->duplicate,
        ];
    }
}
