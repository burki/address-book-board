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

    #[Route('/company/{id}', name: 'company_show')]
    public function detailAction(
        int $id,
        EntityManagerInterface $em
    ): Response {
        return $this->render('Company/detail.html.twig', [
            'company' => $em->getRepository(Company::class)->find($id)
        ]);
    }
}
