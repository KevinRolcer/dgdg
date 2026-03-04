<?php

namespace App\Services\MesasPaz;

use RuntimeException;

class MesasPazServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly array $extraPayload = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function payload(): array
    {
        return $this->extraPayload;
    }
}
