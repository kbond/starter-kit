<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Form\UserProfileForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/profile', name: 'user_profile')]
final class ProfileController extends AbstractController
{
    public function __invoke(
        Request $request,
        Session $session,
        EntityManagerInterface $em,

        #[CurrentUser]
        User $user,
    ): Response {
        $form = $this->createForm(UserProfileForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $session->getFlashBag()->add('success', 'You\'ve successfully updated your profile.');

            return $this->redirectToRoute('user_profile');
        }

        if ($form->isSubmitted()) {
            // form submitted but not valid, ensure modified user isn't
            // fetched or accidentally persisted later in this request
            $em->detach($user);
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form,
        ]);
    }
}
