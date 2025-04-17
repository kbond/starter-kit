<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Form\RegistrationForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register', name: 'register')]
final class RegistrationController extends AbstractController
{
    public function __invoke(
        Request $request,
        Session $session,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,
    ) {
        $user = new User();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('password')->getData();

            // encode the plain password
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $em->persist($user);
            $em->flush();
            $session->getFlashBag()->add('success', 'Registration successful! You are now logged in.');

            return $security->login($user, 'form_login');
        }

        return $this->render('user/register.html.twig', [
            'form' => $form,
        ]);
    }
}
