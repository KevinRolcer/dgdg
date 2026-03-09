<?php

namespace App\Services\MesasPaz;

use RuntimeException;

class MesasPazServiceException extends RuntimeException
{
    private int $httpStatus;
    private array $extraPayload;

    public function __construct(
        string $message,
        int $httpStatus = 422,
        array $extraPayload = []
    ) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->extraPayload = $extraPayload;
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
