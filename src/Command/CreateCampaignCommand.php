<?php

namespace App\Command;

use App\Entity\Campaign;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-campaign',
    description: 'Create a new campaign',
)]
class CreateCampaignCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create a new campaign with name, description, start and end dates');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Create New Campaign');

        $name = $io->ask('Campaign name', null, function ($answer) {
            if (empty(trim($answer))) {
                throw new \RuntimeException('Campaign name cannot be empty.');
            }
            return $answer;
        });

        $description = $io->ask('Campaign description (optional)', '');

        $startDate = $io->ask('Start date (Y-m-d H:i:s)', null, function ($answer) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $answer);
            if (!$date) {
                throw new \RuntimeException('Invalid date format. Use Y-m-d H:i:s (e.g., 2024-01-15 10:00:00)');
            }
            return $date;
        });

        $endDate = $io->ask('End date (Y-m-d H:i:s)', null, function ($answer) use ($startDate) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $answer);
            if (!$date) {
                throw new \RuntimeException('Invalid date format. Use Y-m-d H:i:s (e.g., 2024-01-20 18:00:00)');
            }
            if ($date <= $startDate) {
                throw new \RuntimeException('End date must be after start date.');
            }
            return $date;
        });

        $campaign = new Campaign();
        $campaign->setName($name);
        $campaign->setDescription($description ?: null);
        $campaign->setStartDate($startDate);
        $campaign->setEndDate($endDate);

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        $io->success(sprintf('Campaign "%s" created successfully with ID: %d', $name, $campaign->getId()));

        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $campaign->getId()],
                ['Name', $campaign->getName()],
                ['Description', $campaign->getDescription() ?: 'N/A'],
                ['Start Date', $campaign->getStartDate()->format('Y-m-d H:i:s')],
                ['End Date', $campaign->getEndDate()->format('Y-m-d H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }
}