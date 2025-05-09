<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Form\ChangeEmailForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ChangeEmailController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/change-email', name: 'change_email')]
    public function changeEmail(
        Request $request,
        EntityManagerInterface $em,
        Security $security,

        #[CurrentUser]
        User $user,
    ) {
        $form = $this->createForm(ChangeEmailForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            // since the user identifier changed, we need to re-authenticate
            $security->login($user, 'form_login');

            $this->addFlash('success', 'You have successfully changed your email.');

            return $this->forward(VerificationController::class.'::sendVerification');
        }

        if ($form->isSubmitted()) {
            // form submitted but not valid, ensure modified user isn't
            // accidentally persisted later in this request
            $em->detach($user);
        }

        return $this->render('user/change_email.html.twig', [
            'form' => $form,
        ]);
    }
}
