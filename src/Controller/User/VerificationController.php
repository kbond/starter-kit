<?php

namespace App\Controller\User;

use App\Entity\User;
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
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use function Symfony\Component\Clock\now;

final class VerificationController extends AbstractController
{
    private const EXPIRES_AFTER = 3600 * 24; // 24 hours
    private const SESSION_USER_ID = 'verification_id';

    #[Route('/send-verification', name: 'app_send_verification', methods: 'POST')]
    public function sendVerification(
        UriSigner $uriSigner,
        MailerInterface $mailer,

        #[CurrentUser]
        User $user,

        #[Target('verifyEmailLimiter')]
        RateLimiterFactoryInterface $rateLimiter,
    ): Response {
        if ($user->isVerified()) {
            return $this->redirectToRoute('app_homepage');
        }

        if (!$rateLimiter->create($user->getEmail())->consume()->isAccepted()) {
            $this->addFlash('error', 'You recently requested an account verification. Please check your email for the link to verify your account.');

            return $this->redirectToRoute('app_homepage');
        }

        $this->sendVerificationLink($uriSigner, $mailer, $user);
        $this->addFlash('success', sprintf('A verification link has been sent to %s.', $user->getEmail()));

        return $this->redirectToRoute('app_homepage');
    }

    #[Route('/verify-email/{id<\d+>}', name: 'app_verify_email')]
    public function verify(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        Security $security,
        UriSigner $uriSigner,
        ?int $id = null,
    ): Response {
        if (null !== $id) {
            return $this->addUserIdToSessionAndRedirect($uriSigner, $request, $id);
        }

        if (!$user = $users->find($request->getSession()->get(self::SESSION_USER_ID, 0))) {
            throw $this->createNotFoundException('User not found');
        }

        if ($user->isVerified()) {
            $this->addFlash('error', 'This verification link has already been used.');

            return $this->redirectToRoute('app_homepage');
        }

        $user->markVerified();
        $em->flush();
        $this->addFlash('success', 'Your email has been verified successfully.');
        $request->getSession()->remove(self::SESSION_USER_ID);

        return $security->login($user, 'form_login');
    }

    /**
     * Session-based redirection to the password reset form.
     *
     * This ensures only browser sessions can access the reset form.
     */
    private function addUserIdToSessionAndRedirect(UriSigner $uriSigner, Request $request, int $id): Response
    {
        if (!$uriSigner->checkRequest($request)) {
            $this->addFlash('error', 'The verification link is invalid or has expired, try resending.');

            return $this->redirectToRoute('app_homepage');
        }

        $request->getSession()->set(self::SESSION_USER_ID, $id);

        return $this->redirectToRoute('app_verify_email');
    }

    private function sendVerificationLink(
        UriSigner $uriSigner,
        MailerInterface $mailer,
        User $user,
    ): void {
        $expires = now()->modify(sprintf('+%d seconds', self::EXPIRES_AFTER));
        $link = $uriSigner->sign(
            $this->generateUrl(
                'app_verify_email',
                ['id' => $user->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            $expires
        );

        $message = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('Email Verification')
            ->htmlTemplate('email/verify_email.html.twig')
            ->context([
                'user' => $user,
                'link' => $link,
                'expires' => $expires,
            ])
        ;

        // add Mailer tag header (many email services use these to filter emails)
        $message->getHeaders()->add(new TagHeader('verify-email'));

        // add Mailer metadata with the link (many email services display these in their UI)
        $message->getHeaders()->add(new MetadataHeader('link', $link));

        $mailer->send($message);
    }
}
