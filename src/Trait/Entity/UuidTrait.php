<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait UuidTrait
{
    #[ORM\Column(
        type: 'uuid',
        unique: true,
        options: ['default' => 'uuidv7()'],
        columnDefinition: 'UUID NOT NULL DEFAULT uuidv7()'
    )]
    public Uuid|null $uuid = null {
        set(?Uuid $value) => $this->uuid = $value ?? Uuid::v7();
    }
}
