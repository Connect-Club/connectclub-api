<?php

namespace App\Command;

use App\Entity\User\SmsVerification;
use App\Repository\User\SmsVerificationRepository;
use App\Service\IpQualityScoreClient;
use App\Service\PhoneNumberManager;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use MaxMind\Db\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckSmsVerificationsCommand extends Command
{
    protected static $defaultName = 'CheckSmsVerificationsCommand';
    protected static $defaultDescription = 'Add a short description for your command';

    private PhoneNumberManager $phoneNumberManager;
    private SmsVerificationRepository $smsVerificationRepository;
    private IpQualityScoreClient $ipQualityScoreClient;
    private EntityManagerInterface $entityManager;
    private Reader $reader;

    public function __construct(
        PhoneNumberManager $phoneNumberManager,
        SmsVerificationRepository $smsVerificationRepository,
        IpQualityScoreClient $ipQualityScoreClient,
        EntityManagerInterface $entityManager,
        Reader $reader
    ) {
        parent::__construct(self::$defaultName);

        $this->phoneNumberManager = $phoneNumberManager;
        $this->smsVerificationRepository = $smsVerificationRepository;
        $this->ipQualityScoreClient = $ipQualityScoreClient;
        $this->entityManager = $entityManager;
        $this->reader = $reader;
    }


    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $verifications = $this->smsVerificationRepository
                              ->createQueryBuilder('s')
                              ->where('s.ip IS NOT NULL')
                              ->getQuery()
                              ->getResult();

        $table = [];

        $score = [];

        /** @var SmsVerification $verification */
        foreach ($verifications as $verification) {
            if (isset($score[$verification->ip])) {
                $verification->fraudScore = $score[$verification->ip];
            } else {
                $verification->fraudScore =
                $score[$verification->ip] = $this->ipQualityScoreClient->calculateFraudScore($verification);
            }

            $this->entityManager->persist($verification);
        }

        $this->entityManager->flush();

        $io->table(['Phone number', 'Phone ISO', 'IP', 'IP ISO'], $table);

        return Command::SUCCESS;
    }
}
