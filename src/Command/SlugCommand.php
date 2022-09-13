<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SlugCommand extends Command
{
    protected static $defaultName = 'SlugCommand';
    protected static $defaultDescription = 'Add a short description for your command';

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('str', InputArgument::OPTIONAL, 'String for slugify')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $str = $input->getArgument('str');

        $slugger = new AsciiSlugger();
        $output->writeln(mb_strtolower($slugger->slug($str)));

        return Command::SUCCESS;
    }
}
