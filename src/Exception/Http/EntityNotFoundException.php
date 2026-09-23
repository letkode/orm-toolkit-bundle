<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Exception\Http;

final class EntityNotFoundException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string|null $errorCode = null,
        \Throwable|null $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return 404;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode ?? 'ENTITY_NOT_FOUND';
    }
}
