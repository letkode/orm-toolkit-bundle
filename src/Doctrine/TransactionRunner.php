<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Runs a callback inside a Doctrine transaction, distinguishing an expected domain
 * rejection from a genuine failure when deciding how to recover the EntityManager
 * afterward.
 *
 * EntityManagerInterface::wrapInTransaction() closes the EntityManager on ANY exception,
 * including a routine business rejection — leaving it unusable for the rest of the
 * request (breaking, for example, a kernel.terminate listener that still needs to
 * persist/flush, such as activity logging). A recoverable rejection means the entities
 * touched so far are not corrupt, just that the business operation was rejected — so
 * this only rolls back and clears the identity map, keeping the EntityManager usable.
 * Any other exception keeps Doctrine's default, safety-first behavior.
 *
 * Stateless and EntityManager-agnostic on purpose: pass whichever EntityManager (Hub's
 * default or a tenant's) the caller already has — no per-EM service wiring needed. It is
 * also unaware of any concrete exception class — the caller lists which of its own
 * exception types count as a recoverable rejection.
 */
final readonly class TransactionRunner
{
    /**
     * @template T
     *
     * @param callable(): T                  $fn
     * @param list<class-string<\Throwable>> $recoverableExceptions exception types (or their parents) treated as an expected business rejection
     *
     * @return T
     */
    public function run(EntityManagerInterface $entityManager, callable $fn, array $recoverableExceptions = []): mixed
    {
        $entityManager->beginTransaction();

        try {
            $result = $fn();
            $entityManager->flush();
            $entityManager->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->isRecoverable($exception, $recoverableExceptions)) {
                $entityManager->rollback();
                $entityManager->clear();
            } else {
                $entityManager->close();
                $entityManager->rollback();
            }

            throw $exception;
        }
    }

    /**
     * @param list<class-string<\Throwable>> $recoverableExceptions
     */
    private function isRecoverable(\Throwable $exception, array $recoverableExceptions): bool
    {
        foreach ($recoverableExceptions as $recoverableException) {
            if ($exception instanceof $recoverableException) {
                return true;
            }
        }

        return false;
    }
}
