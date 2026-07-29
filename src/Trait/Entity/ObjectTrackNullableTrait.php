<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait ObjectTrackNullableTrait
{
    #[ORM\Column(name: 'object_class', length: 255, nullable: true)]
    #[Groups(['object_track'])]
    public string|null $objectClass = null;

    #[ORM\Column(name: 'object_id', nullable: true)]
    #[Groups(['object_track'])]
    public int|null $objectId = null;

    public function getObjectTrackToString(): string|null
    {
        if (!$this->objectClass || !$this->objectId) {
            return null;
        }

        return \sprintf('%s-%s', $this->objectClass, $this->objectId);
    }

    /** @return array<string, string|int|null> */
    public function toArrayObjectTrack(): array
    {
        if (null === $this->objectClass) {
            return [
                'object_class' => $this->objectClass,
                'object_id' => $this->objectId,
            ];
        }

        return [
            'object_track' => $this->getObjectTrackToString(),
            'object_class' => $this->objectClass,
            'object_id' => $this->objectId,
        ];
    }
}
