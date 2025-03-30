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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Clock\now;

final class ResetPasswordController extends AbstractController
{
    private const EXPIRES_AFTER = 3600; // 1 hour
    private const SESSION_ID = 'password_reset_id';
    private const ALREADY_USED_HASH_PARAM = 'valid';

    public function __construct(
        private UriSigner $uriSigner,
        private MailerInterface $mailer,
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    #[Route('/forgot-password', name: 'password_reset_request')]
    public function forgot(
        Request $request,
        Session $session,

        #[Target('forgotPasswordEmailLimiter')]
        RateLimiterFactory $rateLimiter, // todo switch to RateLimiterInterface in 7.3
    ): Response {
        $form = $this->createForm(ForgotPasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            if (!$rateLimiter->create($email)->consume()->isAccepted()) {
                $session->getFlashBag()->add('warning', 'You recently requested a password reset. Please check your email for the link to reset your password.');

                return $this->redirectToRoute('password_reset_request');
            }

            $this->sendResetLink($email);

            $session->getFlashBag()->add('note', \sprintf('A password reset link has been sent to %s.', $email));

            return $this->redirectToRoute('homepage');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reset-password/{id<\d+>}', name: 'password_reset')]
    public function reset(
        Request $request,
        Session $session,
        ?int $id = null,
    ): Response {
        if (null !== $id) {
            return $this->addIdToSessionAndRedirect($request, $session, $id);
        }

        if (!$user = $this->users->find($session->get(self::SESSION_ID, 0))) {
            throw $this->createNotFoundException('User not found');
        }

        if (!$alreadyUsedHash = $request->query->getString(self::ALREADY_USED_HASH_PARAM)) {
            throw $this->createNotFoundException('Already used hash not set');
        }

        if (!hash_equals($this->computeAlreadyUsedHash($user), $alreadyUsedHash)) {
            $session->getFlashBag()->add('error', 'This password reset link has already been used.');

            return $this->redirectToRoute('password_reset_request');
        }

        $form = $this->createForm(ChangePasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $this->em->flush();
            $session->remove(self::SESSION_ID);
            $session->getFlashBag()->add('success', 'Your password has been reset successfully, you are now logged in.');

            return $this->security->login($user, 'form_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    private function addIdToSessionAndRedirect(Request $request, Session $session, int $id): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            $session->getFlashBag()->add('error', 'The link is invalid or has expired, please try again.');

            return $this->redirectToRoute('password_reset_request');
        }

        $session->set(self::SESSION_ID, $id);

        return $this->redirectToRoute('password_reset', [self::ALREADY_USED_HASH_PARAM => $request->query->get(self::ALREADY_USED_HASH_PARAM)]);
    }

    private function sendResetLink(string $email): void
    {
        if (!$user = $this->users->findOneBy(['email' => $email])) {
            return;
        }

        $expires = now()->modify(sprintf('+%d seconds', self::EXPIRES_AFTER));
        $link = $this->uriSigner->sign(
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
            ->to($user->getEmailAddress())
            ->subject('Password Reset Request')
            ->htmlTemplate('email/reset_password.html.twig')
            ->context([
                'user' => $user,
                'link' => $link,
                'expires' => $expires,
            ])
        ;
        $message->getHeaders()->add(new TagHeader('forgot-password'));
        $message->getHeaders()->add(new MetadataHeader('link', $link));

        $this->mailer->send($message);
    }

    private function computeAlreadyUsedHash(User $user): string
    {
        return base64_encode(hash_hmac('sha256', $user->getUserIdentifier(), $user->getPassword(), true));
    }
}
