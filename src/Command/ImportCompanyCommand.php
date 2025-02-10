<?php
// src/Command/ImportCompanyCommand.php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Spatie\SimpleExcel\SimpleExcelReader;

use App\Entity\Company;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'app:import:company')]
class ImportCompanyCommand extends Command
{
    protected $updateExisting = true;

    protected EntityManagerInterface $entityManager;
    protected int $countPersists = 0;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the command description shown when running "php bin/console list"
            ->setDescription('Import company from tsv.')
            // the command help shown when running the command with the "--help" option
            ->setHelp('This inserts/updates company table.')
            ->addArgument('fname', InputArgument::REQUIRED, 'File name to convert.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fname = $input->getArgument('fname');
        if (!file_exists($fname)) {
            $output->writeln(sprintf('File %s does not exist.', $fname));

            return Command::FAILURE;
        }

        $companyRepository = $this->entityManager->getRepository(Company::class);

        $reader = SimpleExcelReader::create($fname, 'csv')
            ->useDelimiter("\t")
            // ->take(5) // just for testing
            ;

        // $rows is an instance of Illuminate\Support\LazyCollection
        $rows = $reader->getRows();

        $rows->each(function(array $row) use ($companyRepository) {
            static $count = 0;

            if (!array_key_exists('nodeType', $row) || $row['nodeType'] !== 'company') {
                return;
            }

            $name_full = rtrim($row['name']);

            $company = $companyRepository->findOneBy([ 'nameFull' => $name_full]);

            if ($company === null) {
                $company = new \App\Entity\Company();
                $company->setFullname($name_full);
            }
            else if (!$this->updateExisting) {
                return;
            }

            $company->setName($row['name']); // TODO: maybe shorten by place

            echo ++$count . ': '. $company->getFullname() . "\n";

            $this->entityManager->persist($company);
            if (++$this->countPersists % 20 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->countPersists = 0;
            }
        });

        if ($this->countPersists > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        return Command::SUCCESS;
    }
}
