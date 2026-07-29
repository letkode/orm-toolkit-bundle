<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Uid\Uuid;

/**
 * Ensures the UUID is generated in PHP (not delegated to PostgreSQL default)
 * before any entity is persisted. This allows Gedmo Loggable to capture the
 * uuid value in the "create" log entry, since Gedmo reads PHP property values
 * at flush time — before the DB default is applied.
 *
 * Applies to any entity that has a uuid property (via UuidTrait) whose value
 * is still null at persist time.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class UuidGeneratorSubscriber
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!property_exists($entity, 'uuid') || null !== $entity->uuid) {
            return;
        }

        $entity->uuid = Uuid::v7();
    }
}
