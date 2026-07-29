<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait ParameterTrait
{
    /** @var array<string|int, mixed> */
    #[ORM\Column(options: ['jsonb' => true])]
    #[Gedmo\Versioned]
    private array $parameters = [];

    /** @param array<string|int, mixed> $parameters */
    public function setParameters(array $parameters, bool $force = false): static
    {
        $this->parameters = !$force
            ? array_replace_recursive($this->parameters, $parameters)
            : $parameters;

        return $this;
    }

    /** @return array<string|int, mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string|int $key, mixed $default = null): mixed
    {
        return $this->getParameters()[$key] ?? $default;
    }

    public function setParameter(string|int $key, mixed $value): static
    {
        $params = $this->getParameters();
        $params[$key] = $value;
        $this->setParameters($params);

        return $this;
    }
}
