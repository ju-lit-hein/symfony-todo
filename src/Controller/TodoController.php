<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\User;
use App\Entity\Task;
use Symfony\Component\HttpFoundation\Response;

class TodoController extends AbstractController
{
    #[Route('/', name: 'app_todo', methods: ['GET'], defaults: ['_format' => 'json'])]
    public function index(ManagerRegistry $doctrine): JsonResponse
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
        return $this->json($data);
    }

    #[Route('/register', name: 'app_todo_register', methods: ['POST'])]
    public function register(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $username = $request->request->get('username');
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $user = $doctrine->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            throw $this->createNotFoundException('User already exists');
        }
        $user = new \App\Entity\User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($password);
        $user->setCreationDate(new \DateTime());
        $doctrine->getManager()->persist($user);
        $doctrine->getManager()->flush();
        $data = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ];
        return $this->json($data);
    }

    #[Route('/login', name: 'app_todo_login', methods: ['POST'])]
    public function login(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $user = $doctrine->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['username' => $email]);
        }
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        if ($user->getPassword() !== $password) {
            throw $this->createNotFoundException('Invalid password');
        }
        $data = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ];
        return $this->json($data);
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
        ];
        return $this->json($data);
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
