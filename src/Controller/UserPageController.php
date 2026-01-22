<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/userpage')]
#[IsGranted('ROLE_USER')]
final class UserPageController extends AbstractController
{
    #[Route('/', name: 'app_user_page')]
    public function index(ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $ownedProducts = $productRepository->findBy(['owner' => $user], ['id' => 'DESC'], 6);

        $stats = [
            'ownedProducts' => count($ownedProducts),
            'totalProducts' => $productRepository->count([]),
        ];

        return $this->render('user_page/index.html.twig', [
            'controller_name' => 'UserPageController',
            'user' => $user,
            'ownedProducts' => $ownedProducts,
            'stats' => $stats,
            'isStaff' => in_array('ROLE_STAFF', $user->getRoles(), true),
        ]);
    }

    #[Route('/profile', name: 'app_user_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('user_page/profile.html.twig', [
            'user' => $user,
            'isStaff' => in_array('ROLE_STAFF', $user->getRoles(), true),
        ]);
    }
}
