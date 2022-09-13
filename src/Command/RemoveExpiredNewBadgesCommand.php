<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveExpiredNewBadgesCommand extends Command
{
    use LockableTrait;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct('app:remove-expired-new-badges');
    }

    protected function configure()
    {
        $this
            ->setDescription('Remove expired "new" badges')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $sql = <<<SQL
            UPDATE users
            SET
                badges = badges::jsonb - 'new',
                delete_new_badge_at = null
            WHERE
                delete_new_badge_at IS NOT NULL
                AND delete_new_badge_at <= :time
        SQL;

        $query = $this->entityManager->createNativeQuery($sql, new ResultSetMapping());
        $query->setParameter('time', time());
        $query->execute();

        $this->release();

        return Command::SUCCESS;
    }
}
