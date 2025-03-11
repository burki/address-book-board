<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Company;
use App\Entity\Person;

class DefaultController extends AbstractController
{
    const PAGE_LIMIT = 100;

    #[Route('/', name: 'home')]
    public function homeAction(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // initialize a query builder
        $personCount = $em
            ->getRepository(Person::class)
            ->createQueryBuilder('p')
            ->select('count(p.id)')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $companyCount = $em
            ->getRepository(Company::class)
            ->createQueryBuilder('c')
            ->select('count(c.id)')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $this->render('Default/home.html.twig', [
            'personCount' => $personCount[1],
            'companyCount' => $companyCount[1],
        ]);
    }

    #[Route('/about', name: 'about')]
    public function aboutAction(
        Request $request
    ): Response {
        return $this->render('Default/about.html.twig');
    }
}
