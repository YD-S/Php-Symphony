<?php
namespace App\Tests\Command;

use App\Command\CreateCampaignCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateCampaignCommandTest extends TestCase
{
    private function createCommandTesterWithCommand(
        array $mockMethods = [],
        ?EntityManagerInterface $entityManager = null,
        ?ValidatorInterface $validator = null
    ): array {
        $entityManager = $entityManager ?? $this->createMock(EntityManagerInterface::class);
        $validator = $validator ?? $this->createMock(ValidatorInterface::class);

        $mockedCommand = $this->getMockBuilder(CreateCampaignCommand::class)
            ->setConstructorArgs([$entityManager, $validator])
            ->onlyMethods($mockMethods)
            ->getMock();

        $mockedCommand->setName('app:create-campaign');

        $application = new Application();
        $application->add($mockedCommand);

        $command = $application->find('app:create-campaign');
        $commandTester = new CommandTester($command);

        return [$commandTester, $mockedCommand];
    }

    public function testSuccessfulCampaignCreation(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameters')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);


        $query->method('getResult')->willReturn([]);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand(
            ['askName', 'askDescription', 'askDate'],
            $entityManager,
            $validator
        );

        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameters')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->method('getResult')->willReturn([]);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand(
            ['askName', 'askDescription', 'askDate'],
            $entityManager,
            $validator
        );
        $mockedCommand->method('askName')->willReturn('Summer Promo');
        $mockedCommand->method('askDescription')->willReturn('Promo description');
        $mockedCommand->method('askDate')->will($this->onConsecutiveCalls(
            new \DateTimeImmutable('2021-06-01'),
            new \DateTimeImmutable('2029-06-10')
        ));
        $exitCode = $commandTester->execute([]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Campaign "Summer Promo" created successfully', $commandTester->getDisplay());
    }

    public function testValidationFails(): void
    {
        $violation = new ConstraintViolation(
            'Name cannot be blank',
            null,
            [],
            '',
            'name',
            null
        );

        $violations = new ConstraintViolationList([$violation]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand(
            ['askName', 'askDescription', 'askDate'],
            $entityManager,
            $validator
        );

        $mockedCommand->method('askName')->willReturn('');
        $mockedCommand->method('askDescription')->willReturn('');
        $mockedCommand->method('askDate')->will($this->onConsecutiveCalls(
            new \DateTimeImmutable('2025-06-01'),
            new \DateTimeImmutable('2025-06-10')
        ));

        $exitCode = $commandTester->execute([]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Campaign validation failed', $commandTester->getDisplay());
        $this->assertStringContainsString('Name cannot be blank', $commandTester->getDisplay());
    }

    public function testUnexpectedException(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willThrowException(new \Exception('Unexpected DB error'));

        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand(
            ['askName', 'askDescription', 'askDate'],
            null,
            $validator
        );

        $mockedCommand->method('askName')->willReturn('Oops');
        $mockedCommand->method('askDescription')->willReturn('Some desc');
        $mockedCommand->method('askDate')->will($this->onConsecutiveCalls(
            new \DateTimeImmutable('2025-06-01'),
            new \DateTimeImmutable('2025-06-10')
        ));

        $exitCode = $commandTester->execute([]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unexpected DB error', $commandTester->getDisplay());
    }

    public function testEndDateBeforeStartDateValidationFails(): void
    {
        $violation = new ConstraintViolation(
            'End date must be after start date',
            null,
            [],
            '',
            'endDate',
            null
        );

        $violations = new ConstraintViolationList([$violation]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand(
            ['askName', 'askDescription', 'askDate'],
            $entityManager,
            $validator
        );

        $mockedCommand->method('askName')->willReturn('Date Fail Campaign');
        $mockedCommand->method('askDescription')->willReturn('Invalid dates');
        $mockedCommand->method('askDate')->will($this->onConsecutiveCalls(
            new \DateTimeImmutable('2025-06-10'), // startDate
            new \DateTimeImmutable('2025-06-01')  // endDate before startDate
        ));

        $exitCode = $commandTester->execute([]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Campaign validation failed', $commandTester->getDisplay());
        $this->assertStringContainsString('End date must be after start date', $commandTester->getDisplay());
    }

}
