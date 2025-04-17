<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\ChangePasswordForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/change-password', name: 'change_password')]
class ChangePasswordController extends AbstractController
{
    public function __invoke(
        Request $request,
        Session $session,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,

        #[CurrentUser]
        User $user,
    ): Response {
        $form = $this->createForm(ChangePasswordForm::class, options: [
            'include_current' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $em->flush();

            $session->getFlashBag()->add('success', 'You\'ve successfully changed your password.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('security/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
