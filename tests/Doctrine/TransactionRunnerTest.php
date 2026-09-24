<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Letkode\OrmToolkitBundle\Doctrine\TransactionRunner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TransactionRunnerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TransactionRunner $runner;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->runner = new TransactionRunner();
    }

    public function testCommitsAndReturnsResultOnSuccess(): void
    {
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');
        $this->entityManager->expects($this->never())->method('rollback');
        $this->entityManager->expects($this->never())->method('close');
        $this->entityManager->expects($this->never())->method('clear');

        $result = $this->runner->run($this->entityManager, static fn (): string => 'ok');

        $this->assertSame('ok', $result);
    }

    public function testListedExceptionRollsBackAndClearsWithoutClosing(): void
    {
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->once())->method('clear');
        $this->entityManager->expects($this->never())->method('close');
        $this->entityManager->expects($this->never())->method('commit');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $this->runner->run(
            $this->entityManager,
            static function (): void {
                throw new \InvalidArgumentException('expected domain rejection');
            },
            recoverableExceptions: [\InvalidArgumentException::class],
        );
    }

    public function testMatchesAnyExceptionInTheList(): void
    {
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->once())->method('clear');
        $this->entityManager->expects($this->never())->method('close');

        $this->expectException(\InvalidArgumentException::class);

        $this->runner->run(
            $this->entityManager,
            static function (): void {
                throw new \InvalidArgumentException('expected domain rejection');
            },
            recoverableExceptions: [\RuntimeException::class, \InvalidArgumentException::class],
        );
    }

    public function testUnlistedExceptionClosesAndRollsBack(): void
    {
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('close');
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('clear');
        $this->entityManager->expects($this->never())->method('commit');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\RuntimeException::class);

        $this->runner->run(
            $this->entityManager,
            static function (): void {
                throw new \RuntimeException('genuine failure');
            },
            recoverableExceptions: [\InvalidArgumentException::class],
        );
    }

    public function testNoRecoverableExceptionsListedAlwaysCloses(): void
    {
        $this->entityManager->expects($this->once())->method('close');
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('clear');

        $this->expectException(\InvalidArgumentException::class);

        $this->runner->run($this->entityManager, static function (): void {
            throw new \InvalidArgumentException('no recoverable exceptions declared');
        });
    }
}
