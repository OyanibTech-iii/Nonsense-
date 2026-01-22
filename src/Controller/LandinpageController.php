<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandinpageController extends AbstractController
{
    #[Route('/', name: 'app_landinpage')]
    public function index(Request $request): Response
    {
        // Always render the main index template with all content sections
        return $this->render('landinpage/index.html.twig', [
            'controller_name' => 'LandinpageController',
        ]);
    }
    #[Route('/about', name: 'app_about')]
    public function about(Request $request): Response
    {
        return $this->render('landinpage/index.html.twig', [
            'controller_name' => 'LandinpageController',
            'page' => 'about',
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request): Response
    {
        return $this->render('landinpage/index.html.twig', [
            'controller_name' => 'LandinpageController',
            // map to the template's section name (plural) used by the SPA nav
            'page' => 'contacts',
        ]);
    }

    #[Route('/products', name: 'app_products')]
    public function products(Request $request): Response
    {
        return $this->render('landinpage/index.html.twig', [
            'controller_name' => 'LandinpageController',
            'page' => 'products',
        ]);
    }
}
