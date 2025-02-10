<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Person
 */
#[ORM\Table(name: 'person', options: ['collate' => 'utf8mb4_unicode_520_ci'])]
#[ORM\Entity]
#[UniqueEntity('nameFull')]
class Person
{
    public function __construct()
    {
        $this->companyRelations = new ArrayCollection();
    }

    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name_full', type: 'string', length: 511, nullable: false, unique: true, options: ['collation' => 'utf8mb4_bin'])]
    private $nameFull;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: 'string', length: 511, nullable: false)]
    private $name;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'description', type: 'string', length: 511, nullable: true)]
    private $description;

    /**
     * @var array|null
     */
    #[ORM\Column(name: 'info_by_year', type: 'json', nullable: true)]
    private $infoByYear;

    /**
     * @var PersonCompany[]
     */
    #[ORM\OneToMany(targetEntity: PersonCompany::class, mappedBy: 'person', cascade: ['persist'], fetch: 'EAGER', )]
    public $companyRelations;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setFullname($nameFull): self
    {
        $this->nameFull = $nameFull;

        return $this;
    }

    public function getFullname(): string
    {
        return $this->nameFull;
    }

    public function setInfoByYear($info, $year = null): self
    {
        if (is_null($year)) {
            $this->infoByYear = $info;
        } else {
            if (is_null($this->infoByYear)) {
                $this->infoByYear = [];
            }

            $this->infoByYear[$year] = $info;
        }

        return $this;
    }

    public function getInfoByYear($year = null): array|null
    {
        if (is_null($year)) {
            return $this->infoByYear;
        }

        return $this->infoByYear[$year] ?? null;
    }

    public function addCompanyRelation(PersonCompany $personCompany): self
    {
        $this->companyRelations[] = $personCompany;

        return $this;
    }
}
