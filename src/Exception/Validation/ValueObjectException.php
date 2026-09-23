<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Exception\Validation;

final class ValueObjectException extends \InvalidArgumentException
{
    /**
     * @param string               $message           human-readable English message (for logs/traces)
     * @param string               $translationKey    key in the "validators" translation domain
     * @param array<string, mixed> $translationParams placeholder values for the translated string
     */
    public function __construct(
        string $message,
        public readonly string $translationKey,
        public readonly array $translationParams = [],
    ) {
        parent::__construct($message);
    }
}
