<?php

namespace Yiendos\MySitesIde\Monitoring\Tempo\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Tempo\Ide;
use Yiendos\MySitesIde\Monitoring\Tempo\Traits\InteractsWithTempo;

class TempoStartCommand extends Command
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
            ->setName('monitoring:tempo-start')
            ->setDescription('Write Tempo\'s config for the monitoring plugins installed, then start Tempo')
        ;
    }

    /**
     * Writes storage/plugins/tempo/conf/tempo.yaml from the stub. The
     * metrics-generator (service graphs, span metrics) is only switched on when
     * the prometheus plugin is installed - without it every remote write would
     * fail - so run this again after adding or removing Prometheus. Tempo only
     * reads its config when it starts, so a running Tempo is restarted when
     * it's changed.
     *
     * `up -d --build` is a no-op for an up-to-date container, recreates one
     * whose compose config changed, and builds the image after an update to
     * the Dockerfile.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $metrics = Ide::installed('prometheus');

        $config = strtr((string) file_get_contents(Ide::package('stubs/tempo.yaml')), [
            '__RETENTION__' => getenv('TEMPO_RETENTION') ?: '48h',
            "__METRICS_GENERATOR__\n" => $metrics ? (string) file_get_contents(Ide::package('stubs/metrics-generator.yaml')) : '',
        ]);

        $changed = Ide::write('conf/tempo.yaml', $config);
        $wasRunning = $this->running();

        $io->writeln($metrics
            ? 'Service graphs and span metrics go to Prometheus'
            : 'No service graphs or span metrics - the prometheus plugin isn\'t installed');

        if ($this->compose($output, 'up -d --build tempo') !== 0) {
            $io->error('Tempo did not start - see above.');
            return Command::FAILURE;
        }

        if ($changed && $wasRunning && $this->compose($output, 'restart tempo') !== 0) {
            $io->error('Tempo did not restart with the new config - see above.');
            return Command::FAILURE;
        }

        $io->success('Tempo started - OTLP on tempo:4317 (gRPC) and tempo:4318 (HTTP) inside the IDE');

        return Command::SUCCESS;
    }
}
