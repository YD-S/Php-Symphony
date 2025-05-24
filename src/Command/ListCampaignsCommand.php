<?php

namespace App\Command;

use App\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'app:list-campaigns',
    description: 'List all campaigns with their details',
    aliases: ['app:campaigns', 'app:ls-campaigns']
)]
class ListCampaignsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private CampaignRepository $campaignRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CampaignRepository $campaignRepository
    ) {
        $this->entityManager = $entityManager;
        $this->campaignRepository = $campaignRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sort', 's', InputOption::VALUE_REQUIRED, 'Sort by field (id, name, start_date, end_date)', 'id')
            ->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Sort order (asc/desc)', 'asc')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of campaigns to display', '100')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by campaign status (active/past/upcoming)', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $campaigns = $this->getCampaigns(
                $input->getOption('sort'),
                $input->getOption('order'),
                (int)$input->getOption('limit'),
                $input->getOption('status')
            );

            if (empty($campaigns)) {
                $io->warning('No campaigns found.');
                return Command::SUCCESS;
            }

            $io->title('List of Campaigns');

            $tableRows = $this->prepareTableRows($campaigns);

            $io->table(
                ['ID', 'Name', 'Description', 'Start Date', 'End Date', 'Influencers Count'],
                $tableRows
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('Error listing campaigns: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function getCampaigns(
        string $sort = 'id',
        string $order = 'asc',
        int $limit = 100,
        ?string $status = null
    ): array {
        $qb = $this->campaignRepository->createQueryBuilder('c')
            ->select('c', 'COUNT(i.id) AS influencer_count')
            ->leftJoin('c.influencers', 'i')
            ->groupBy('c.id')
            ->setMaxResults($limit);

        if ($status) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        $qb->orderBy('c.' . $sort, $order);

        return $qb->getQuery()->getResult();
    }

    private function prepareTableRows(array $campaigns): array
    {
        $rows = [];
        foreach ($campaigns as $result) {
            $campaign = $result[0];
            $influencerCount = $result['influencer_count'];

            $rows[] = [
                $campaign->getId(),
                $campaign->getName(),
                $campaign->getDescription() ?: 'N/A',
                $campaign->getStartDate()->format('Y-m-d H:i:s'),
                $campaign->getEndDate()->format('Y-m-d H:i:s'),
                $influencerCount,
            ];
        }
        return $rows;
    }
}