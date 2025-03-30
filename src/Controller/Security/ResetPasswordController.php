<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\ChangePasswordForm;
use App\Form\ForgotPasswordForm;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Clock\now;

final class ResetPasswordController extends AbstractController
{
    private const EXPIRES_AFTER = 3600; // 1 hour
    private const SESSION_USER_ID_KEY = 'password_reset_id';
    private const ALREADY_USED_HASH_PARAM = 'valid';

    #[Route('/forgot-password', name: 'password_reset_request')]
    public function forgot(
        Request $request,
        UriSigner $uriSigner,
        MailerInterface $mailer,
        UserRepository $users,

        #[Target('forgotPasswordEmailLimiter')]
        RateLimiterFactory $rateLimiter, // todo switch to RateLimiterInterface in 7.3
    ): Response {
        $form = $this->createForm(ForgotPasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            if (!$rateLimiter->create($email)->consume()->isAccepted()) {
                $this->addFlash('error', 'You recently requested a password reset. Please check your email for the link to reset your password.');

                return $this->redirectToRoute('password_reset_request');
            }

            $this->sendResetLink($users, $uriSigner, $mailer, $email);

            $this->addFlash('success', \sprintf('A password reset link has been sent to %s.', $email));

            return $this->redirectToRoute('homepage');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reset-password/{id<\d+>}', name: 'password_reset')]
    public function reset(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        EntityManagerInterface $em,
        UriSigner $uriSigner,
        ?int $id = null,
    ): Response {
        if (null !== $id) {
            return $this->addUserIdToSessionAndRedirect($uriSigner, $request, $id);
        }

        if (!$user = $users->find($request->getSession()->get(self::SESSION_USER_ID_KEY, 0))) {
            throw $this->createNotFoundException('User not found');
        }

        if (!$alreadyUsedHash = $request->query->getString(self::ALREADY_USED_HASH_PARAM)) {
            throw $this->createNotFoundException('Already used hash not set');
        }

        if (!hash_equals($this->computeAlreadyUsedHash($user), $alreadyUsedHash)) {
            $this->addFlash('error', 'This password reset link has already been used.');

            return $this->redirectToRoute('password_reset_request');
        }

        $form = $this->createForm(ChangePasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $em->flush();
            $request->getSession()->remove(self::SESSION_USER_ID_KEY);
            $this->addFlash('success', 'Your password has been reset successfully, you are now logged in.');

            return $security->login($user, 'form_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    /**
     * Session-based redirection to the password reset form.
     *
     * This ensures only browser sessions can access the reset form.
     */
    private function addUserIdToSessionAndRedirect(UriSigner $uriSigner, Request $request, int $id): Response
    {
        if (!$uriSigner->checkRequest($request)) {
            $this->addFlash('error', 'The link is invalid or has expired, please try again.');

            return $this->redirectToRoute('password_reset_request');
        }

        $request->getSession()->set(self::SESSION_USER_ID_KEY, $id);

        return $this->redirectToRoute('password_reset', [self::ALREADY_USED_HASH_PARAM => $request->query->get(self::ALREADY_USED_HASH_PARAM)]);
    }

    private function sendResetLink(
        UserRepository $users,
        UriSigner $uriSigner,
        MailerInterface $mailer,
        string $email,
    ): void {
        if (!$user = $users->findOneBy(['email' => $email])) {
            return;
        }

        $expires = now()->modify(sprintf('+%d seconds', self::EXPIRES_AFTER));
        $link = $uriSigner->sign(
            $this->generateUrl(
                'password_reset',
                [
                    'id' => $user->getId(),
                    self::ALREADY_USED_HASH_PARAM => $this->computeAlreadyUsedHash($user),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            $expires
        );
        $message = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('Password Reset Request')
            ->htmlTemplate('email/reset_password.html.twig')
            ->context([
                'user' => $user,
                'link' => $link,
                'expires' => $expires,
            ])
        ;

        // add Mailer tag header (many email services use these to filter emails)
        $message->getHeaders()->add(new TagHeader('forgot-password'));

        // add Mailer metadata with the link (many email services display these in their UI)
        $message->getHeaders()->add(new MetadataHeader('link', $link));

        $mailer->send($message);
    }

    private function computeAlreadyUsedHash(User $user): string
    {
        return base64_encode(hash_hmac('sha256', $user->getUserIdentifier(), $user->getPassword(), true));
    }
}
