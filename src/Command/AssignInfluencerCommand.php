<?php

namespace App\Command;

use App\Entity\Campaign;
use App\Entity\Influencer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-influencer',
    description: 'Assign an influencer to a campaign',
)]
class AssignInfluencerCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('campaignId', InputArgument::REQUIRED, 'The ID of the campaign')
            ->addArgument('influencerId', InputArgument::REQUIRED, 'The ID of the influencer')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompts')
            ->setHelp('This command allows you to assign an influencer to a specific campaign.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $campaignId = $this->validateId($input->getArgument('campaignId'), 'Campaign');
            $influencerId = $this->validateId($input->getArgument('influencerId'), 'Influencer');

            $campaign = $this->findCampaign($campaignId);
            $influencer = $this->findInfluencer($influencerId);

            if ($this->isInfluencerAlreadyAssigned($campaign, $influencer)) {
                $io->warning(sprintf(
                    'Influencer "%s" is already assigned to campaign "%s".',
                    $influencer->getName(),
                    $campaign->getName()
                ));
                return Command::SUCCESS;
            }

            if (!$input->getOption('force') && !$this->confirmAssignment($io, $campaign, $influencer)) {
                $io->note('Assignment cancelled.');
                return Command::SUCCESS;
            }

            $this->performAssignment($campaign, $influencer);

            $io->success(sprintf(
                'Influencer "%s" has been successfully assigned to campaign "%s".',
                $influencer->getName(),
                $campaign->getName()
            ));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function validateId(mixed $id, string $entityType): int
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            throw new \InvalidArgumentException(
                sprintf('%s ID must be a positive integer. Got: %s', $entityType, $id)
            );
        }

        return (int)$id;
    }

    private function findCampaign(int $id): Campaign
    {
        $campaign = $this->entityManager->getRepository(Campaign::class)->find($id);

        if (!$campaign) {
            throw new \RuntimeException(sprintf('Campaign with ID %d not found.', $id));
        }

        return $campaign;
    }

    private function findInfluencer(int $id): Influencer
    {
        $influencer = $this->entityManager->getRepository(Influencer::class)->find($id);

        if (!$influencer) {
            throw new \RuntimeException(sprintf('Influencer with ID %d not found.', $id));
        }

        return $influencer;
    }

    private function isInfluencerAlreadyAssigned(Campaign $campaign, Influencer $influencer): bool
    {
        return $campaign->getInfluencers()->contains($influencer);
    }

    private function confirmAssignment(SymfonyStyle $io, Campaign $campaign, Influencer $influencer): bool
    {
        $io->table(
            ['Property', 'Value'],
            [
                ['Campaign ID', $campaign->getId()],
                ['Campaign Name', $campaign->getName()],
                ['Influencer ID', $influencer->getId()],
                ['Influencer Name', $influencer->getName()],
            ]
        );

        return $io->confirm('Do you want to proceed with this assignment?', true);
    }

    private function performAssignment(Campaign $campaign, Influencer $influencer): void
    {
        try {
            $this->entityManager->beginTransaction();

            $campaign->addInfluencer($influencer);
            $this->entityManager->persist($campaign);
            $this->entityManager->flush();

            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new \RuntimeException(
                sprintf('Failed to assign influencer to campaign: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}