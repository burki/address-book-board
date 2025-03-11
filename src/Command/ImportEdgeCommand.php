<?php

// src/Command/ImportEdgeCommand.php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Spatie\SimpleExcel\SimpleExcelReader;
use App\Entity\Person;
use App\Entity\Company;
use App\Entity\PersonCompany;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'app:import:edge')]
class ImportEdgeCommand extends Command
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
            ->setDescription('Import edges from tsv.')
            // the command help shown when running the command with the "--help" option
            ->setHelp('This inserts/updates person_company table.')
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
        $companyRepository = $this->entityManager->getRepository(Company::class);
        $personCompanyRepository = $this->entityManager->getRepository(PersonCompany::class);

        $reader = SimpleExcelReader::create($fname, 'csv')
            ->useDelimiter("\t")
            // ->take(5) // just for testing
        ;

        // $rows is an instance of Illuminate\Support\LazyCollection
        $rows = $reader->getRows();

        $rows->each(function (array $row) use ($personRepository, $companyRepository, $personCompanyRepository) {
            static $count = 0;

            if (empty($row['person']) || empty($row['company']) || empty($row['year'])) {
                return;
            }

            $person_name = rtrim($row['person'], ' ,');
            $person = $personRepository->findOneBy([
                'nameFull' => $person_name,
            ]);
            if (is_null($person)) {
                echo 'No person found for: ' . $person_name . "\n";
            }

            $company_name = rtrim($row['company'], ' .');
            $company = $companyRepository->findOneBy([
                'nameFull' => $company_name,
            ]);
            if (is_null($company)) {
                echo 'No company found for: ' . $company_name . "\n";
            }

            $year = $row['year'];

            $personCompany = $personCompanyRepository->findOneBy([
                'person' => $person,
                'company' => $company,
                'year' => $year,
            ]);

            if (null === $personCompany) {
                $personCompany = new PersonCompany();
                $personCompany->setPerson($person);
                $personCompany->setCompany($company);
                $personCompany->setYear($year);
            }
            else if (!$this->updateExisting) {
                return;
            }

            $personCompany->setRelationship($row['relationship'] ?? null);
            $personCompany->setIsBoard('True' === $row['isAufsichtsrat']);

            echo ++$count . ': ' . $person->getFullname() . '->' . $company->getFullname() . "\n";

            $this->entityManager->persist($company);
            if (0 === ++$this->countPersists % 20) {
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
