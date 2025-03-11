<?php

// src/Controller/PersonController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Company;
use App\Filter\PersonFilterType;
use App\Entity\Person;

class PersonController extends AbstractController
{
    const PAGE_LIMIT = 100;

    #[Route('/person', name: 'person_list')]
    public function listAction(
        Request $request,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $em,
        FilterBuilderUpdater $filterBuilderUpdater,
        PaginatorInterface $paginator
    ): Response {
        // initialize a query builder
        $filterBuilder = $em
            ->getRepository(Person::class)
            ->createQueryBuilder('p')
            ->select('p, p.name AS name, COUNT(pc) AS numCompanies')
            ->leftJoin('p.companyRelations', 'pc')
            ->groupBy('p.id')
        ;

        $form = $formFactory->create(PersonFilterType::class);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->all()[$form->getName()]);

            // build the query from the given form object
            $filterBuilderUpdater->addFilterConditions($form, $filterBuilder);
        }

        $pagination = $paginator->paginate(
            $filterBuilder->getQuery(),
            $request->query->get('page', 1),
            self::PAGE_LIMIT,
            [
                'defaultSortFieldName' => 'name',
                'defaultSortDirection' => 'asc',
            ]
        );

        return $this->render('Person/list.html.twig', [
            'form' => $form->createView(),
            'pagination' => $pagination,
        ]);
    }

    protected function findSimilar(EntityManagerInterface $em, Person $entity)
    {
        $dbconn = $em->getConnection();

        // build all the ids
        $company_ids = [];
        foreach ($entity->getCompanyRelations() as $personCompany) {
            $company_ids[] = $personCompany->getCompany()->getId();
        }

        $num_companies = count($company_ids);
        if (0 == $num_companies) {
            return $company_ids;
        }

        $querystr = 'SELECT person_id, company_id'
                . ' FROM person_company'
                . ' WHERE company_id IN (' . join(', ', $company_ids) . ')'
                . ' AND person_id <> ' . $entity->getId()
                . ' ORDER BY person_id';

        $companies_by_person = [];
        $stmt = $dbconn->executeQuery($querystr);
        while ($row = $stmt->fetchAssociative()) {
            if (!array_key_exists($row['person_id'], $companies_by_person)) {
                $companies_by_person[$row['person_id']] = [];
            }

            if (!in_array($row['company_id'], $companies_by_person[$row['person_id']])) {
                // person can appear in multiple roles, maybe add DISTINCT to query above
                $companies_by_person[$row['person_id']][] = $row['company_id'];
            }
        }

        $jaccard_index = [];
        $person_ids = array_keys($companies_by_person);
        if (count($person_ids) > 0) {
            $querystr = 'SELECT name, person_id, COUNT(DISTINCT company_id) AS num_companies'
                    . ' FROM person_company'
                    . ' LEFT OUTER JOIN person ON person_company.person_id=person.id'
                        . ' WHERE person_id IN (' . join(', ', $person_ids) . ')'
                    . ' GROUP BY person_id';
            $stmt = $dbconn->executeQuery($querystr);
            while ($row = $stmt->fetchAssociative()) {
                $num_shared = count($companies_by_person[$row['person_id']]);
                $jaccard_index[$row['person_id']] = [
                    'name' => $row['name'],
                    'count' => $num_shared,
                    'coefficient' => 1.0
                            * $num_shared // shared
                            /
                            ($row['num_companies'] + $num_companies - $num_shared),
                ];
            }

            uasort(
                $jaccard_index,
                function ($a, $b) {
                    if ($a['coefficient'] == $b['coefficient']) {
                        return 0;
                    }

                    // highest first
                    return $a['coefficient'] < $b['coefficient'] ? 1 : -1;
                }
            );
        }

        return $jaccard_index;
    }

    #[Route('/person/{id}', name: 'person_show')]
    public function detailAction(
        int $id,
        EntityManagerInterface $em
    ): Response {
        $entity = $em->getRepository(Person::class)->find($id);
        if (is_null($entity)) {
            throw $this->createNotFoundException('Person not found');
        }

        return $this->render('Person/detail.html.twig', [
            'person' => $entity,
            'similar' => $this->findSimilar($em, $entity),
        ]);
    }

    #[Route('/person/shared/{companies}', name: 'person_shared')]
    public function sharedAction(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator,
        $companies = null
    ) {
        if (!is_null($companies)) {
            $companies = explode(',', $companies);
        }

        if (is_null($companies) || count($companies) < 2) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid argument');
        }

        $entities = [];
        $names = [];
        $companyRepo = $em->getRepository(Company::class);
        for ($i = 0; $i < 2; ++$i) {
            $criteria = new \Doctrine\Common\Collections\Criteria();

            $criteria->where($criteria->expr()->eq('id', $companies[$i]));

            $matching = $companyRepo->matching($criteria);
            if (0 == count($matching)) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid argument');
            }

            $entity = $matching[0];
            $entities[] = $entity;
            $names[] = $entity->getName();
        }

        $qb = $em
                ->createQueryBuilder();

        $qb->select([
            'P',
            'P.name AS name',
        ])
            ->from('App\Entity\Person', 'P')
            ->innerJoin(
                'App\Entity\PersonCompany',
                'PC1',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'PC1.person = P AND PC1.company = :company1'
            )
            ->innerJoin(
                'App\Entity\PersonCompany',
                'PC2',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'PC2.person = P AND PC2.company = :company2'
            )
            ->setParameters(['company1' => $entities[0], 'company2' => $entities[1]])
            ->groupBy('P.id')
            ->orderBy('name')
        ;

        $pagination = $paginator->paginate(
            $qb->getQuery(),
            $request->query->get('page', 1),
            self::PAGE_LIMIT,
            [
                'defaultSortFieldName' => 'name',
                'defaultSortDirection' => 'asc',
            ]
        );

        return $this->render('Person/shared.html.twig', [
            'pageTitle' => 'Gemeinsame Personen von'
                . ' ' . implode(' und ', $names),
            'pagination' => $pagination,
        ]);
    }
}
