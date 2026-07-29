<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait ObjectTrackRequiredTrait
{
    #[ORM\Column(name: 'object_class', length: 255)]
    #[Groups(['object_track'])]
    public string $objectClass;

    #[ORM\Column(name: 'object_id')]
    #[Groups(['object_track'])]
    public int $objectId;

    public function getObjectTrackToString(): string
    {
        return \sprintf('%s-%s', $this->objectClass, $this->objectId);
    }

    /** @return array{object_track: string, object_class: string, object_id: int} */
    public function toArrayObjectTrack(): array
    {
        return [
            'object_track' => $this->getObjectTrackToString(),
            'object_class' => $this->objectClass,
            'object_id' => $this->objectId,
        ];
    }
}
