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

class PlaceController extends AbstractController
{
    const PAGE_LIMIT = 10000;

    #[Route('/place', name: 'place_list')]
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
            ->select('c.infoByYear, JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".placeNameGeocoded\')) AS name, JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".osmID\')) AS osmID, COUNT(c.id) AS numCompanies')
            // ->where('JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".placeNameGeocoded\')) <> \'\'')
            ->groupBy('osmID')
            ->orderBy('name')
        ;

        /*
        $form = $formFactory->create(CompanyFilterType::class);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->all()[$form->getName()]);

            // build the query from the given form object
            $filterBuilderUpdater->addFilterConditions($form, $filterBuilder);
        }
        */

        $query =  $filterBuilder->getQuery();

        // see https://github.com/KnpLabs/KnpPaginatorBundle/issues/392
        $query->setHint('knp_paginator.count', 999);

        $pagination = $paginator->paginate(
            $query,
            $request->query->get('page', 1),
            self::PAGE_LIMIT,
            [
                'distinct' => false,
                'defaultSortFieldName' => 'numCompanies',
                'defaultSortDirection' => 'desc',
            ]
        );

        return $this->render('Place/list.html.twig', [
            // 'form' => $form->createView(),
            'pagination' => $pagination,
        ]);
    }

    #[Route('/place/map', name: 'place_map')]
    public function mapAction(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $data = [];

        $qb = $em
            ->getRepository(Company::class)
            ->createQueryBuilder('c')
            ->select('c.infoByYear, JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".placeNameGeocoded\')) AS name, JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".osmID\')) AS osmID, COUNT(c.id) AS numCompanies')
            ->groupBy('osmID')
        ;

        foreach ($qb->getQuery()->getResult() as $row) {
            $infoByYear = $row['infoByYear'];
            if (!array_key_exists('1926', $infoByYear)) {
                continue;
            }
            $info = $infoByYear['1926'];
            [$lat, $lon] = [$info['lat'], $info['lon']];
            if (empty($lat) || empty($lon)) {
                continue;
            }

            $title = sprintf(
                '<a href="%s">%s</a>',
                $this->generateUrl('place_show', [ 'osmID' => $info['osmID'] ]),
                htmlspecialchars($row['name'], ENT_COMPAT, 'utf-8'),
            );

            $info = [
                (float) $lat, (float) $lon,
                $title,
                $row['numCompanies'],
            ];

            $data[] = $info;
        }

        return $this->render('Place/map.html.twig', [
            'data' => $data,
            'disableClusteringAtZoom' => 8,
            'bounds' => [
                [ 54, 12 ],
                [ 48, 9 ],
            ],
        ]);
    }

    protected function findCompaniesByPlace(
        int $osmID,
        EntityManagerInterface $em
    ) {
        $qb = $em
            ->getRepository(Company::class)
            ->createQueryBuilder('c')
            ->select('c, c.name AS name, COUNT(pc) AS numPersons')
            ->leftJoin('c.personRelations', 'pc')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".osmID\')) = :osmID')
            ->setParameter('osmID', $osmID)
            ->groupBy('c.id')
        ;

        return $qb->getQuery()->getResult();
    }

    protected function buildPersonsForCompanies(
        $companies,
        EntityManagerInterface $em
    ) {
        $companyIds = array_map(function ($company) { return $company->getId(); }, $companies);
        $qb = $em
            ->getRepository(Person::class)
            ->createQueryBuilder('p')
            ->select('p, p.name AS name, COUNT(DISTINCT pc) AS numCompaniesPlace, COUNT(DISTINCT pc2) AS numCompanies, COUNT(DISTINCT pc) / COUNT(DISTINCT pc2) AS percentageCompanies')
            ->leftJoin('p.companyRelations', 'pc')
            ->where('pc.company IN (:companyIds)')
            ->setParameter('companyIds', $companyIds)
            ->leftJoin('p.companyRelations', 'pc2')
            ->groupBy('p.id')
            ->orderBy('numCompaniesPlace', 'DESC')
            ->addOrderBy('percentageCompanies', 'DESC')
        ;

        return $qb->getQuery()->getResult();
    }

    #[Route('/place/{osmID}', name: 'place_show')]
    public function detailAction(
        int $osmID,
        EntityManagerInterface $em
    ): Response {
        $res = $em->createQueryBuilder()
            ->select('c.infoByYear')
            ->from(Company::class, 'c')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(c.infoByYear, \'$."1926".osmID\')) = :osmID')
            ->setParameter('osmID', $osmID)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();

        if (null === $res) {
            throw $this->createNotFoundException('Place not found');
        }

        $companies = $this->findCompaniesByPlace($osmID, $em);

        return $this->render('Place/detail.html.twig', [
            'info' => $res['infoByYear'],
            'companies' => $companies,
            'persons' => $this->buildPersonsForCompanies(
                array_map(function ($row) { return $row[0]; }, $companies),
                $em
            ),
        ]);
    }
}
