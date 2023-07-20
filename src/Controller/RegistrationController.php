<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\LoginFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $entityManager->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user != null) {
                return $this->redirectToRoute('app_todo');
            }
        }
        $user = new User();
        $isUserJustDeleted = $request->query->get('deleted') == 1;
        if ($isUserJustDeleted) {
            $this->addFlash('delete-user-success', 'User deleted successfully');
        }
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'attr' => ['class' => 'space-y-6'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(hash('sha256', $form->get('plainPassword')->getData()));
            $user->setCreationDate(new \DateTime());

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            // $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            //     (new TemplatedEmail())
            //         ->from(new Address('no-reply@todo.etib.tech', 'no-reply-etib-corp-todo-app'))
            //         ->to($user->getEmail())
            //         ->subject('Please Confirm your Email')
            //         ->htmlTemplate('registration/confirmation_email.html.twig')
            // );
            // do anything else you need here, like send an email
            setcookie('loginToken', $user->getPassword(), time() + (86400 * 30), '/');

            return $this->redirectToRoute('app_todo');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /*#[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
    }*/

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $entityManager->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user != null) {
                return  $this->redirectToRoute('app_todo');
            }
        }
        $user = new User();
        $form = $this->createForm(LoginFormType::class, $user, [
            'attr' => ['class' => 'space-y-6'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $username = $form->get('username')->getData();
            $goodUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($goodUser == null) {
                $this->addFlash('login-error', 'Username or password is incorrect');
                return $this->redirectToRoute('app_login', ['invalid' => 1]);
            }
            $user->setPassword(hash('sha256', $form->get('plainPassword')->getData()));
            if ($user->getPassword() != $goodUser->getPassword()) {
                $this->addFlash('login-error', 'Username or password is incorrect');
                return $this->redirectToRoute('app_login', ['invalid' => 1]);
            }
            setcookie('loginToken', $user->getPassword(), time() + (86400 * 30), '/');

            return $this->redirectToRoute('app_todo');
        }

        return $this->render('registration/login.html.twig', [
            'loginForm' => $form,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): Response
    {
        setcookie('loginToken', '', time() - 3600, '/');
        return $this->redirectToRoute('app_login');
    }
}
