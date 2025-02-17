<?php
// src/Controller/CompanyController.php
namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use Knp\Component\Pager\PaginatorInterface;

use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;


use App\Filter\CompanyFilterType;
use App\Entity\Company;
use App\Entity\Person;

class CompanyController extends AbstractController
{
    const PAGE_LIMIT = 100;

    #[Route('/company', name: 'company_list')]
    public function listAction(
        Request $request,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $em,
        FilterBuilderUpdater $filterBuilderUpdater,
        PaginatorInterface $paginator
    ): Response {
        // initialize a query builder
        $filterBuilder = $em
            ->getRepository(Company::class)
            ->createQueryBuilder('c')
            ->select('c, c.name AS name, COUNT(pc) AS numPersons')
            ->leftJoin('c.personRelations', 'pc')
            ->groupBy('c.id')
            ;

        $form = $formFactory->create(CompanyFilterType::class);

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

        return $this->render('Company/list.html.twig', [
            'form' => $form->createView(),
            'pagination' => $pagination
        ]);
    }

    protected function findSimilar(EntityManagerInterface $em, Company $entity)
    {
        $dbconn = $em->getConnection();

        // build all the ids
        $person_ids = [];
        foreach ($entity->getPersonRelations() as $personCompany) {
            $person_ids[] = $personCompany->getPerson()->getId();
        }

        if (0 == count($person_ids)) {
            return $person_ids;
        }

        $querystr = "SELECT person_id, company_id"
            . " FROM person_company"
            . " WHERE person_id IN (" . join(', ', $person_ids) . ')'
            . " AND company_id <> " . $entity->getId()
            . " ORDER BY company_id";
        $num_persons = count($person_ids);

        $persons_by_company = [];
        $stmt = $dbconn->executeQuery($querystr);
        while ($row = $stmt->fetchAssociative()) {
            if (!array_key_exists($row['company_id'], $persons_by_company)) {
                $persons_by_company[$row['company_id']] = [];
            }

            $persons_by_company[$row['company_id']][] = $row['person_id'];
        }

        $jaccard_index = [];
        $company_ids = array_keys($persons_by_company);
        if (count($company_ids) > 0) {
            $querystr = "SELECT name, company_id, COUNT(DISTINCT person_id) AS num_persons"
                . " FROM person_company"
                . " LEFT OUTER JOIN company ON person_company.company_id=company.id"
                . " WHERE company_id IN (" . join(', ', $company_ids) . ')'
                . " GROUP BY company_id";
            $stmt = $dbconn->executeQuery($querystr);
            while ($row = $stmt->fetchAssociative()) {
                $num_shared = count($persons_by_company[$row['company_id']]);
                $jaccard_index[$row['company_id']] = [
                    'name' => $row['name'],
                    'count' => $num_shared,
                    'coefficient' =>
                    1.0
                        * $num_shared // shared
                        /
                        ($row['num_persons'] + $num_persons - $num_shared)
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

    #[Route('/company/{id}', name: 'company_show')]
    public function detailAction(
        int $id,
        EntityManagerInterface $em
    ): Response
    {
        $entity =  $em->getRepository(Company::class)->find($id);
        if (is_null($entity)) {
            throw $this->createNotFoundException('Company not found');
        }

        return $this->render('Company/detail.html.twig', [
            'company' => $entity,
            'similar' => $this->findSimilar($em, $entity),
        ]);
    }

    #[Route('/company/shared/{persons}', name: 'company_shared')]
    public function sharedAction(Request $request,
                                 EntityManagerInterface $em,
                                 PaginatorInterface $paginator,
                                 $persons = null)
    {
        if (!is_null($persons)) {
            $persons = explode(',', $persons);
        }

        if (is_null($persons) || count($persons) < 2) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Invalid argument");
        }

        $entities = [];
        $names = [];
        $personRepo = $em->getRepository(Person::class);
        for ($i = 0; $i < 2; $i++) {
            $criteria = new \Doctrine\Common\Collections\Criteria();

            $criteria->where($criteria->expr()->eq('id', $persons[$i]));

            $matching = $personRepo->matching($criteria);
            if (0 == count($matching)) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Invalid argument");
            }

            $entity = $matching[0];
            $entities[] = $entity;
            $names[] = $entity->getName();
        }

        $qb = $em
                ->createQueryBuilder();

        $qb->select([
                'C',
                "C.name AS name"
            ])
            ->from('App\Entity\Company', 'C')
            ->innerJoin('App\Entity\PersonCompany', 'PC1',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'PC1.company = C AND PC1.person = :person1')
            ->innerJoin('App\Entity\PersonCompany', 'PC2',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'PC2.company = C AND PC2.person = :person2')
            ->setParameters([ 'person1' => $entities[0], 'person2' => $entities[1] ])
            ->groupBy('C.id')
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

        return $this->render('Company/shared.html.twig', [
            'pageTitle' => 'Gemeinsame Firmen von'
                . ' ' . implode(' und ', $names),
            'pagination' => $pagination,
        ]);
    }
}
