<?php

namespace App\Command;

use App\Repository\Ethereum\TokenRepository;
use App\Service\InfuraClient;
use Ethereum\DataType\EthD;
use Ethereum\DataType\EthQ;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestInfuraCommand extends Command
{
    protected static $defaultName = 'TestInfura';
    protected static $defaultDescription = 'Add a short description for your command';

    private TokenRepository $tokenRepository;
    private InfuraClient $infuraClient;

    public function __construct(TokenRepository $tokenRepository, InfuraClient $infuraClient, string $name = null)
    {
        $this->tokenRepository = $tokenRepository;
        $this->infuraClient = $infuraClient;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        $token = $this->tokenRepository->find($id);
        $contract = $this->infuraClient->getSmartContractClient($token);

        $output->writeln($contract->getAddress());

        $balanceOf = $contract->balanceOf(//@phpstan-ignore-line
            new EthD('0x2953399124f0cbb46d2cbacd8a89cf0599974963'),
            new EthQ($token->tokenId),
        )->val();

        var_export($balanceOf);

        return Command::SUCCESS;
    }
}
