<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Form\RegistrationForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,

        #[Target('userRegistrationLimiter')]
        RateLimiterFactory $rateLimiter, // todo switch to RateLimiterInterface in 7.3
    ) {
        $user = new User();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ip = $request->getClientIp();

            if ($ip && !$rateLimiter->create($ip)->consume()->isAccepted()) {
                $this->addFlash('error', 'You recently registered. Please try again later.');

                return $this->redirectToRoute('homepage');
            }

            /** @var string $plainPassword */
            $plainPassword = $form->get('password')->getData();

            // encode the plain password
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Registration successful! You are now logged in.');

            $security->login($user, 'form_login');

            return $this->forward(VerificationController::class.'::sendVerification');
        }

        return $this->render('user/register.html.twig', [
            'form' => $form,
        ]);
    }
}
