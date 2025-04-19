<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\LogoutOtherForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/logout-other', name: 'logout_other')]
final class LogoutOtherController extends AbstractController
{
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,

        #[CurrentUser]
        User $user,
    ): Response {
        $form = $this->createForm(LogoutOtherForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('password')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $em->flush();

            $this->addFlash('success', 'Other devices have been logged out.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('security/logout_other.html.twig', [
            'form' => $form,
        ]);
    }
}
