<?php

namespace A8nx\CLI\Commands;
use A8nx\Factory\FromYaml;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'workflow:run', description: 'Run workflow')]
class Run extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Run workflow')
            ->addOption(
                'flow',
                'f',
                InputOption::VALUE_REQUIRED,
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $flow = $input->getOption('flow');

        if (!$flow) {
            throw new InvalidArgumentException('flow option is required');
        }

        if (!file_exists($flow)) {
            throw new InvalidArgumentException('invalid flow file');
        }

        $output->writeln('<info>Parsing workflow...</info>');

        $workflow = FromYaml::parse($flow);

        // Пробрасываем IO в контекст воркфлоу
        $workflow->setIo($input, $output);

        $workflow->setVerbose($output->getVerbosity());
        $workflow->run();
        return Command::SUCCESS;
    }

}