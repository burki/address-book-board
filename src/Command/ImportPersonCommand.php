<?php
// src/Command/ImportPersonCommand.php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Spatie\SimpleExcel\SimpleExcelReader;

use App\Entity\Person;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'app:import:person')]
class ImportPersonCommand extends Command
{
    protected static $ACADEMIC_TITLES = [ 'Dr.', 'Prof.', 'Prof. Dr.' ];

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
            ->setDescription('Import person from tsv.')
            // the command help shown when running the command with the "--help" option
            ->setHelp('This inserts/updates person table.')
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

        $personRepository = $this->entityManager->getRepository(Person::class);

        $reader = SimpleExcelReader::create($fname, 'csv')
            ->useDelimiter("\t")
            // ->take(5) // just for testing
            ;

        // $rows is an instance of Illuminate\Support\LazyCollection
        $rows = $reader->getRows();

        $rows->each(function(array $row) use ($personRepository) {
            static $count = 0;

            if (!array_key_exists('nodeType', $row) || $row['nodeType'] !== 'person') {
                return;
            }

            $name_full = rtrim($row['name'], " ,");

            $person = $personRepository->findOneBy([ 'nameFull' => $name_full]);

            if ($person === null) {
                $person = new \App\Entity\Person();
                $person->setFullname($name_full);
            }
            else if (!$this->updateExisting) {
                return;
            }

            $full_parts = explode(', ', $name_full);
            $name_parts = [ $full_parts[0] ];
            $description_parts = [];

            if (count($full_parts) > 1) {
                $name_parts[] = $full_parts[1];

                if (count($full_parts) > 2) {
                    // possibly futher processing
                    /*
                    if (in_array($name_parts[1], self::$ACADEMIC_TITLES)) {
                        echo $name_parts[2] . "\n";
                    }
                    */
                    $description_parts = array_slice($full_parts, 2);
                }
            }

            $person->setName(join(', ', $name_parts));

            $info = [];
            if (count($description_parts) > 0) {
                $info['description'] = join(', ', $description_parts);
            }

            foreach ([ 'address', 'scanPageNo', 'lineID' ] as $key) {
                if (array_key_exists($key, $row) && !is_null($row[$key]) && $row[$key] !== '') {
                    $info[$key] = $row[$key];
                }
            }
            $person->setInfoByYear($info, $row['year']);

            echo ++$count . ': '. $person->getFullname() . "\n";

            $this->entityManager->persist($person);
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
