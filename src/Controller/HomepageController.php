<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'homepage')]
final class HomepageController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('homepage.html.twig');
    }
}
