<?php

// src/Command/ReduceCompanyCsvCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader;
use OpenSpout\Writer\CSV\Writer;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'app:reduce-company-csv')]
class ReduceCompanyCsvCommand extends Command
{
    protected function configure(): void
    {
        $this
            // the command description shown when running "php bin/console list"
            ->setDescription('Strip normalizationCluster from csv.')
            // the command help shown when running the command with the "--help" option
            ->addArgument('fname', InputArgument::REQUIRED, 'File name to reduce.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fname = $input->getArgument('fname');
        if (!file_exists($fname)) {
            $output->writeln(sprintf('File %s does not exist.', $fname));

            return Command::FAILURE;
        }

        $reader = new Reader();
        $reader->open($fname);

        $writer = new Writer();
        $writer->openToFile('php://output');

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                $numCells = count($row->getCells());
                foreach ($row->getCells() as $i => $cell) {
                    if ($i <= 10 || $i == $numCells - 1) {
                        $cells[] = $cell;
                    }
                }

                $writer->addRow(new Row($cells));
            }
        }

        $reader->close();
        $writer->close();

        return Command::SUCCESS;
    }
}
