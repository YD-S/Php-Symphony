<?php

namespace App\Command;

use App\Entity\Campaign;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-campaign',
    description: 'Create a new campaign with validation'
)]
class CreateCampaignCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create New Campaign');

        try {
            $campaign = new Campaign();
            $this->populateCampaignFromInput($io, $campaign);
            $this->validateCampaign($campaign, $io);
            $this->checkDateOverlap($campaign->getStartDate(), $campaign->getEndDate());

            $this->entityManager->persist($campaign);
            $this->entityManager->flush();

            $this->displaySuccessMessage($io, $campaign);

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function askName(SymfonyStyle $io): string
    {
        return $io->ask('Campaign Name', null, function ($name) {
            if (empty($name)) {
                throw new \RuntimeException('Campaign name cannot be empty.');
            }
            return $name;
        });
    }

    protected function askDescription(SymfonyStyle $io): ?string
    {
        return $io->ask('Campaign Description (optional)', null);
    }

    protected function askDate(SymfonyStyle $io, string $prompt, ?\DateTimeInterface $minDate = null): \DateTimeImmutable
    {
        return $io->ask($prompt . ' (YYYY-MM-DD HH:MM:SS)', null, function ($dateString) use ($prompt, $minDate) {
            if (empty($dateString)) {
                throw new \RuntimeException("$prompt cannot be empty.");
            }
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateString);
            if (!$date) {
                throw new \RuntimeException("Invalid date format for $prompt. Use 'YYYY-MM-DD HH:MM:SS'");
            }
            if ($minDate !== null && $date < $minDate) {
                throw new \RuntimeException("$prompt cannot be earlier than " . $minDate->format('Y-m-d H:i:s'));
            }
            return $date;
        });
    }

    protected function checkDateOverlap(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): void
    {
        $qb = $this->entityManager->createQueryBuilder();

        $parameters = new ArrayCollection([
            new Parameter('startDate', $startDate, Types::DATETIME_IMMUTABLE),
            new Parameter('endDate', $endDate, Types::DATETIME_IMMUTABLE)
        ]);

        $qb->select('c')
            ->from(Campaign::class, 'c')
            ->where('c.startDate <= :endDate')
            ->andWhere('c.endDate >= :startDate')
            ->setParameters($parameters);

        $overlappingCampaigns = $qb->getQuery()->getResult();

        if ($overlappingCampaigns === null || count($overlappingCampaigns) > 0) {
            throw new \RuntimeException('There is already a campaign overlapping with those dates.');
        }
    }

    private function populateCampaignFromInput(SymfonyStyle $io, Campaign $campaign): void
    {
        $campaign->setName($this->askName($io));
        $campaign->setDescription($this->askDescription($io));
        $campaign->setStartDate($this->askDate($io, 'Start date'));
        $campaign->setEndDate($this->askDate($io, 'End date', $campaign->getStartDate()));
    }

    private function validateCampaign(Campaign $campaign, SymfonyStyle $io): void
    {
        $errors = $this->validator->validate($campaign);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }
            throw new \RuntimeException('Campaign validation failed');
        }
    }

    private function displaySuccessMessage(SymfonyStyle $io, Campaign $campaign): void
    {
        $io->success(sprintf(
            'Campaign "%s" created successfully with ID: %d',
            $campaign->getName(),
            $campaign->getId()
        ));

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
    }
}