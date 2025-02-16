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
            'pagination' => $pagination
        ]);
    }

    #[Route('/person/{id}', name: 'person_show')]
    public function detailAction(
        int $id,
        EntityManagerInterface $em
    ): Response {
        return $this->render('Person/detail.html.twig', [
            'person' => $em->getRepository(Person::class)->find($id)
        ]);
    }
}
