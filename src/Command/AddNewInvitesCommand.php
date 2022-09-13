<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddNewInvitesCommand extends Command
{
    protected static $defaultName = 'AddNewInvitesCommand';
    protected static $defaultDescription = 'Add new invites command';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityManager->createNativeQuery(
            'UPDATE users SET free_invites = 20,
                              read_notification_new_invites = false
            WHERE free_invites < 20 AND free_invites >= 0 AND state = \''.User::STATE_VERIFIED.'\'',
            new ResultSetMapping()
        )->execute();

        $this->entityManager->createNativeQuery(
            'UPDATE club SET free_invites = 1000 WHERE free_invites < 1000',
            new ResultSetMapping()
        )->execute();

        return Command::SUCCESS;
    }
}
