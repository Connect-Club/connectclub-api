<?php

namespace App\Command;

use App\Entity\VideoChat\VideoMeeting;
use App\Event\SlackNotificationEvent;
use App\Repository\VideoChat\VideoMeetingRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SendSlackStatisticsCommand extends Command
{
    protected static $defaultName = 'SendSlackStatistics';
    protected static $defaultDescription = 'Add a short description for your command';

    private EventDispatcherInterface $dispatcher;
    private VideoMeetingRepository $videoMeetingRepository;

    public function __construct(EventDispatcherInterface $dispatcher, VideoMeetingRepository $videoMeetingRepository)
    {
        parent::__construct(self::$defaultName);

        $this->dispatcher = $dispatcher;
        $this->videoMeetingRepository = $videoMeetingRepository;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('startTime')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $time = strtotime($input->getArgument('startTime'));
        $io->title('UTC time is '.$time.' timezone '.ini_get('date.timezone'));

        if (!$io->confirm('Continue?')) {
            return Command::SUCCESS;
        }

        $io->title('Start handle meetings');
        $meetings = $this->videoMeetingRepository->matching(Criteria::create()->where(
            Criteria::expr()->gte('startTime', $time)
        ))->toArray();
        $io->title('Found '.count($meetings).' meetings');

        $choice = $io->choice('Choose', ['Show all meetings' => 't', 'Continue' => 'y'], 'y');
        if ($choice == 'Show all meetings') {
            $io->table(['Meeting name'], array_map(
                fn(VideoMeeting $vm) => [$vm->videoRoom->community->description],
                $meetings
            ));

            if (!$io->confirm('Continue?')) {
                return Command::SUCCESS;
            }
        }

        foreach ($meetings as $meeting) {
            $this->dispatcher->dispatch(new SlackNotificationEvent($meeting));
        }


        return Command::SUCCESS;
    }
}
