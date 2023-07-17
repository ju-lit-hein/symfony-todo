<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormBuilderInterface;

use App\Entity\User;
use App\Entity\Task;

class TodoController extends AbstractController
{
    #[Route('/', name: 'app_todo', methods: ['GET'])]
    public function index(ManagerRegistry $doctrine): Response
    {
        $users = $doctrine->getRepository(User::class)->findAll();
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'creation_date' => $user->getCreationDate()->format('M-d-Y'),
            ];
        }
        return $this->render('user/index.html.twig', [
            'users' => $data,
        ]);
    }

    #[Route('/register-old', name: 'app_todo_register', methods: ['GET', 'POST'])]
    public function register(ManagerRegistry $doctrine, Request $request): Response
    {
        $user = new User();
        $form = $this->createFormBuilder($user)
            ->add('username')
            ->add('email')
            ->add('password')
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $email = $user->getEmail();
            $username = $user->getUsername();
            $alreadyRegisteredEmail = $doctrine->getRepository(User::class)->findOneBy(['email' => $email]) != null;
            $alreadyRegisteredUsername = $doctrine->getRepository(User::class)->findOneBy(['username' => $username]) != null;
            if ($alreadyRegisteredEmail || $alreadyRegisteredUsername) {
                throw $this->createNotFoundException('User already exists');
            }
            $user->setCreationDate(new \DateTime());
            $doctrine->getManager()->persist($user);
            $doctrine->getManager()->flush();
            return $this->redirectToRoute('app_todo_user', ['id' => $user->getId()]);
        }
        return $this->render('user/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/login', name: 'app_todo_login', methods: ['GET', 'POST'])]
    public function login(ManagerRegistry $doctrine, Request $request, FormBuilderInterface $builder): Response
    {
    }

    #[Route('/user/{id}', name: 'app_todo_user', methods: ['GET'])]
    public function userById(ManagerRegistry $doctrine, string $id): JsonResponse
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $data = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'creation_date' => $user->getCreationDate()->format('M-d-Y'),
        ];
        return $this->json($data);
    }

    #[Route('/user/{id}/edit', name: 'app_todo_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(ManagerRegistry $doctrine, Request $request, string $id): Response
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $form = $this->createFormBuilder($user)
            ->add('username', null, ['required' => false])
            ->add('email', null, ['required' => false])
            ->add('password', null, ['required' => false, 'data' => ""])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $doctrine->getManager()->flush();
            return $this->redirectToRoute('app_todo_user', ['id' => $user->getId(), 'edited' => true]);
        }
        return $this->render('user/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/user/{id}/delete', name: 'app_todo_user_delete', methods: ['GET', 'POST'])]
    public function userDelete(ManagerRegistry $doctrine, string $id): Response
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $doctrine->getManager()->remove($user);
        $doctrine->getManager()->flush();
        return $this->redirectToRoute('app_todo_register', ['deleted' => true]);
    }

    #[Route('/user/{id}/tasks', name: 'app_todo_user_tasks', methods: ['GET'])]
    public function userTasks(ManagerRegistry $doctrine, string $id): JsonResponse
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $tasks = $user->getTasks();
        $data = [];
        foreach ($tasks as $task) {
            $data[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'creation_date' => $task->getCreationDate()->format('M-d-Y'),
                'due_date' => $task->getDueDate()->format('M-d-Y'),
                'status' => $task->getStatus(),
            ];
        }
        return $this->json($data);
    }

    #[Route('/user/{id}/task/{task_id}', name: 'app_todo_user_task', methods: ['GET'])]
    public function userTaskById(ManagerRegistry $doctrine, string $id, string $task_id): JsonResponse
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        $task = $doctrine->getRepository(Task::class)->find($task_id);
        if ($user === null || $task === null || $task->getUser() !== $user) {
            throw $this->createNotFoundException('Task not found');
        }
        $data = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'creation_date' => $task->getCreationDate()->format('M-d-Y'),
            'due_date' => $task->getDueDate()->format('M-d-Y'),
            'status' => $task->getStatus(),
        ];
        return $this->json($data);
    }

    #[Route('/user/{id}/new', name: 'app_todo_user_task_create', methods: ['POST'])]
    public function userTaskCreate(ManagerRegistry $doctrine, Request $request, string $id): JsonResponse
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $name = $request->request->get('name');
        $description = $request->request->get('description');
        $due_date = $request->request->get('due_date');
        $task = new \App\Entity\Task();
        $task->setName($name);
        $task->setDescription($description);
        $task->setCreationDate(new \DateTime());
        $task->setDueDate(new \DateTime($due_date));
        $task->setStatus('not started');
        $task->setUser($user);
        $doctrine->getManager()->persist($task);
        $doctrine->getManager()->flush();
        $data = [
            'id' => $task->getId(),
            'name' => $task->getName(),
            'description' => $task->getDescription(),
            'creation_date' => $task->getCreationDate()->format('Y-m-d H:i:s'),
            'due_date' => $task->getDueDate()->format('Y-m-d H:i:s'),
            'status' => $task->getStatus(),
        ];
        return $this->json($data);
    }

    #[Route('/tasks', name: 'app_todo_tasks', methods: ['GET'])]
    public function tasks(ManagerRegistry $doctrine): JsonResponse
    {
        $tasks = $doctrine->getRepository(Task::class)->findAll();
        $data = [];
        foreach ($tasks as $task) {
            $data[] = [
                'id' => $task->getId(),
                'title' => $task->getName(),
                'description' => $task->getDescription(),
                'creation_date' => $task->getCreationDate()->format('Y-m-d H:i:s'),
                'due_date' => $task->getDueDate()->format('Y-m-d H:i:s'),
                'status' => $task->getStatus(),
            ];
        }
        return $this->json($data);
    }

    #[Route('/task/{id}', name: 'app_todo_task', methods: ['GET'])]
    public function taskById(ManagerRegistry $doctrine, string $id): JsonResponse
    {
        $task = $doctrine->getRepository(Task::class)->find($id);
        if ($task === null) {
            throw $this->createNotFoundException('Task not found');
        }
        $data = [
            'id' => $task->getId(),
            'title' => $task->getName(),
            'description' => $task->getDescription(),
            'creation_date' => $task->getCreationDate()->format('M-d-Y'),
            'due_date' => $task->getDueDate()->format('M-d-Y'),
            'status' => $task->getStatus(),
        ];
        return $this->json($data);
    }
}
