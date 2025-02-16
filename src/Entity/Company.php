<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Company
 */
#[ORM\Table(name: 'company', options: ['collate' => 'utf8mb4_unicode_520_ci'])]
#[ORM\Entity]
#[UniqueEntity('nameFull')]
class Company
{
    public function __construct()
    {
        $this->personRelations = new ArrayCollection();
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
    #[ORM\OneToMany(targetEntity: PersonCompany::class, mappedBy: 'company', cascade: ['persist'], fetch: 'EAGER')]
    public $personRelations;

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

    public function addPersonRelation(PersonCompany $personCompany): self
    {
        $this->personRelations[] = $personCompany;

        return $this;
    }

    public function getPersonRelations(?bool $isBoard = null): iterable
    {
        if (is_null($isBoard)) {
            return $this->personRelations;
        }

        return $this->personRelations->filter(function (PersonCompany $personCompany) use ($isBoard) {
            return $personCompany->getIsBoard() === $isBoard;
        });
    }
}
