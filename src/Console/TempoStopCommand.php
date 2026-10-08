<?php

namespace Yiendos\MySitesIde\Monitoring\Tempo\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Tempo\Traits\InteractsWithTempo;

class TempoStopCommand extends Command
{
    use InteractsWithTempo;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('monitoring:tempo-stop')
            ->setDescription('Stop the Tempo container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so monitoring:tempo-start brings back
     * the same container. Its data is kept in storage/plugins/tempo/
     * either way.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->running()) {
            $io->writeln('Tempo is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop tempo') !== 0) {
            $io->error('Tempo did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('Tempo stopped.');

        return Command::SUCCESS;
    }
}
