<?php

namespace App\Tests\Command;

use App\Command\ListCampaignsCommand;
use App\Entity\Campaign;
use App\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ListCampaignsCommandTest extends TestCase
{
    private function createCommandTesterWithCommand(
        array $mockMethods = [],
        ?EntityManagerInterface $entityManager = null,
        ?CampaignRepository $campaignRepository = null
    ): array {
        $entityManager = $entityManager ?? $this->createMock(EntityManagerInterface::class);
        $campaignRepository = $campaignRepository ?? $this->createMock(CampaignRepository::class);

        $mockedCommand = $this->getMockBuilder(ListCampaignsCommand::class)
            ->setConstructorArgs([$entityManager, $campaignRepository])
            ->onlyMethods($mockMethods)
            ->getMock();

        $mockedCommand->setName('app:list-campaigns');

        $application = new Application();
        $application->add($mockedCommand);

        $command = $application->find('app:list-campaigns');
        $commandTester = new CommandTester($command);

        return [$commandTester, $mockedCommand];
    }

    public function testListCampaignsSuccessfully(): void
    {
        $campaign1 = new Campaign();
        $campaign1->setName('Summer Promo');
        $campaign1->setDescription('Summer campaign description');
        $campaign1->setStartDate(new \DateTimeImmutable('2021-06-01'));
        $campaign1->setEndDate(new \DateTimeImmutable('2021-06-10'));

        $campaign2 = new Campaign();
        $campaign2->setName('Winter Promo');
        $campaign2->setDescription('Winter campaign description');
        $campaign2->setStartDate(new \DateTimeImmutable('2021-12-01'));
        $campaign2->setEndDate(new \DateTimeImmutable('2021-12-10'));

        $campaignRepository = $this->createMock(CampaignRepository::class);
        $campaignRepository->method('createQueryBuilder')->willReturn($this->createQueryBuilderMock([$campaign1, $campaign2]));

        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand([], null, $campaignRepository);

        // Execute the command
        $exitCode = $commandTester->execute([]);

        // Debug output
        echo "\nCommand Output:\n";
        echo $commandTester->getDisplay();

        // Assert the exit code
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('List of Campaigns', $commandTester->getDisplay());
        $this->assertStringContainsString('Summer Promo', $commandTester->getDisplay());
        $this->assertStringContainsString('Winter Promo', $commandTester->getDisplay());
    }

    public function testNoCampaignsFound(): void
    {
        $campaignRepository = $this->createMock(CampaignRepository::class);
        $campaignRepository->method('createQueryBuilder')->willReturn($this->createQueryBuilderMock([]));

        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand([], null, $campaignRepository);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No campaigns found.', $commandTester->getDisplay());
    }

    public function testExceptionHandling(): void
    {
        $campaignRepository = $this->createMock(CampaignRepository::class);
        $campaignRepository->method('createQueryBuilder')->willThrowException(new \Exception('Database error'));

        [$commandTester, $mockedCommand] = $this->createCommandTesterWithCommand([], null, $campaignRepository);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Error listing campaigns: Database error', $commandTester->getDisplay());
    }

    private function createQueryBuilderMock(array $campaigns): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        // Mock the query builder chain methods
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('leftJoin')->willReturn($queryBuilder);
        $queryBuilder->method('groupBy')->willReturn($queryBuilder);
        $queryBuilder->method('setMaxResults')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        // Return campaigns as associative arrays
        $query->method('getResult')->willReturn(array_map(function ($campaign) {
            return [
                0 => $campaign, // numeric index 0 as expected by the command
                'influencer_count' => 0,
            ];
        }, $campaigns));

        return $queryBuilder;
    }
}
