<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Person.
 */
#[ORM\Table(name: 'person_company', options: ['collate' => 'utf8mb4_unicode_520_ci'])]
#[ORM\Entity]
class PersonCompany
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var Person
     *
     **/
    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'companyRelations')]
    private $person;

    /**
     * @var Company
     *
     **/
    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'personRelations')]
    private $company;

    /**
     * @var int
     */
    #[ORM\Column(name: 'year', type: 'integer', nullable: false)]
    private $year;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'relationship', type: 'string', length: 127, nullable: true)]
    private $relationship;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'is_board', type: 'boolean', nullable: false)]
    private $isBoard = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setPerson(Person $person): self
    {
        $this->person = $person;
        $person->addCompanyRelation($this);

        return $this;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        $company->addPersonRelation($this);

        return $this;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setRelationship(?string $relationship): self
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function setIsBoard(bool $isBoard): self
    {
        $this->isBoard = $isBoard;

        return $this;
    }

    public function getIsBoard(): bool
    {
        return $this->isBoard;
    }
}
