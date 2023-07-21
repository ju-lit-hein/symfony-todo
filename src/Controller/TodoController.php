<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\UserDeletionConfirmFormType;
use App\Form\NewTaskFormType;
use App\Form\NewTeamFormType;
use App\Form\NewUserFormType;

use App\Entity\User;
use App\Entity\Task;
use App\Entity\Team;
use Doctrine\ORM\EntityManager;

class TodoController extends AbstractController
{
    #[Route('/', name: 'app_todo')]
    public function index(ManagerRegistry $doctrine, Request $request): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
        }
        $teams = $user->getTeams();
        $data = [];
        foreach ($teams as $team) {
            $data[] = [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'created_by' => $team->getCreatedBy()->getUsername(),
                'img' => $team->getImgPath(),
                'users' => $team->getUsers(),
            ];
        }
        $data = array_reverse($data);
        foreach ($data as $key => $team) {
            $users = $team['users'];
            $newUsers = [];
            foreach ($users as $user) {
                if ($user->getUsername() === $team['created_by']) {
                    $newUsers[] = $user;
                }
            }
            foreach ($users as $user) {
                if ($user->getUsername() !== $team['created_by']) {
                    $newUsers[] = $user;
                }
            }
            $data[$key]['users'] = $newUsers;
        }
        $isNewTeam = $request->query->get('new');
        $form = $this->createForm(NewTeamFormType::class);
        if ($isNewTeam) {
            $id = $user->getId();
            $newTeamUser = $doctrine->getRepository(User::class)->find($id);
            if ($newTeamUser === null) {
                throw $this->createNotFoundException('User not found');
            }
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $name = $form->get('name')->getData();
                $team = new Team();
                $team->setName($name);
                $team->setCreatedBy($newTeamUser);
                //$team->setCreationDate(new \DateTime());
                $team->addUser($user);
                $doctrine->getManager()->persist($team);
                $doctrine->getManager()->flush();
                $this->addFlash('success', 'Team created successfully');
                return $this->redirectToRoute('app_todo', ['new' => false]);
            }
            $this->addFlash('new-team', 'Want to create a new team');
        }
        $isNewUser = $request->query->get('newUser');
        $formNewUser = $this->createForm(NewUserFormType::class);
        if ($isNewUser) {
            $id = $request->query->get('id');
            if ($id === null) {
                throw $this->createNotFoundException('Team not found');
            }
            $team = $doctrine->getRepository(Team::class)->find($id);
            if ($team === null) {
                throw $this->createNotFoundException('Team not found');
            }
            $formNewUser->handleRequest($request);
            if ($formNewUser->isSubmitted() && $formNewUser->isValid()) {
                $username = $formNewUser->get('username')->getData();
                $newUser = $doctrine->getRepository(User::class)->findOneBy(['username' => $username]);
                if ($newUser === null) {
                    throw $this->createNotFoundException('User not found');
                }
                $team->addUser($newUser);
                $doctrine->getManager()->flush();
                $this->addFlash('success', 'User added successfully');
                return $this->redirectToRoute('app_todo', ['newUser' => false]);
            }
            $this->addFlash('new-user', 'Want to add a new user');
        }
        return $this->render('user/index.html.twig', [
            'teams' => $data,
            'form' => $form,
            'formNewUser' => $formNewUser,
        ]);
    }

    #[Route('/team', name: 'app_todo_team')]
    public function teamById(ManagerRegistry $doctrine, Request $request): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                return $this->redirectToRoute('app_login');
            }
        } else {
            return $this->redirectToRoute('app_login');
        }
        $teamId = $request->query->get('team');
        $team = $doctrine->getRepository(Team::class)->find($teamId);
        if ($team === null) {
            throw $this->createNotFoundException('Team not found');
        }
        $users = $team->getUsers();
        $data = [];
        foreach ($users as $user) {
            $tasks = $doctrine->getRepository(Task::class)->findBy(['user' => $user]);
            $data[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'img' => $user->getImgPath(),
                'creation_date' => $user->getCreationDate()->format('M-d-Y'),
                'team_id' => $teamId,
                'tasks' => [],
            ];
            foreach ($tasks as $task) {
                if ($task->getTeamId() != $teamId) {
                    continue;
                }
                $data[count($data) - 1]['tasks'][] = [
                    'id' => $task->getId(),
                    'name' => $task->getName(),
                    'description' => $task->getDescription(),
                    'creation_date' => $task->getCreationDate()->format('M-d-Y'),
                    'due_date' => $task->getDueDate()->format('M-d-Y'),
                    'status' => $task->getStatus(),
                ];
            }
        }
        // put the user who created the team first
        $newUsers = [];
        foreach ($data as $user) {
            if ($user['username'] === $team->getCreatedBy()->getUsername()) {
                $newUsers[] = $user;
            }
        }
        foreach ($data as $user) {
            if ($user['username'] !== $team->getCreatedBy()->getUsername()) {
                $newUsers[] = $user;
            }
        }
        $isNewTask = $request->query->get('new');
        $form = $this->createForm(NewTaskFormType::class);
        if ($isNewTask) {
            $id = $request->query->get('id');
            $newTaskUser = $doctrine->getRepository(User::class)->find($id);
            if ($newTaskUser === null) {
                throw $this->createNotFoundException('User not found');
            }
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $name = $form->get('name')->getData();
                $description = $form->get('description')->getData();
                $due_date = $form->get('dueDate')->getData();
                $status = $form->get('status')->getData();
                $task = new \App\Entity\Task();
                $task->setName($name);
                $task->setDescription($description);
                $task->setCreationDate(new \DateTime());
                $task->setDueDate($due_date);
                $task->setStatus($status);
                $task->setUser($newTaskUser);
                $task->setTeamId($teamId);
                $doctrine->getManager()->persist($task);
                $doctrine->getManager()->flush();
                $this->addFlash('success', 'Task created successfully');
                return $this->redirectToRoute('app_todo_team', ['new' => false, 'team' => $teamId]);
            }
            $this->addFlash('new-task', 'Want to create a new task');
        }
        $isNewUser = $request->query->get('newUser');
        $formNewUser = $this->createForm(NewUserFormType::class);
        if ($isNewUser) {
            $id = $request->query->get('team');
            if ($id === null) {
                throw $this->createNotFoundException('Team not found');
            }
            $team = $doctrine->getRepository(Team::class)->find($id);
            if ($team === null) {
                throw $this->createNotFoundException('Team not found');
            }
            $formNewUser->handleRequest($request);
            if ($formNewUser->isSubmitted() && $formNewUser->isValid()) {
                $username = $formNewUser->get('username')->getData();
                $newUser = $doctrine->getRepository(User::class)->findOneBy(['username' => $username]);
                if ($newUser === null) {
                    throw $this->createNotFoundException('User not found');
                }
                $team->addUser($newUser);
                $doctrine->getManager()->flush();
                $this->addFlash('success', 'User added successfully');
                return $this->redirectToRoute('app_todo_team', ['newUser' => false, 'team' => $teamId]);
            }
            $this->addFlash('new-user', 'Want to add a new user');
        }
        return $this->render('user/team.html.twig', [
            'users' => $newUsers,
            'form' => $form,
            'formNewUser' => $formNewUser,
            'team' => $teamArray = [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'created_by' => $team->getCreatedBy()->getUsername(),
                'img' => $team->getImgPath(),
                'users' => $team->getUsers(),
            ],
        ]);
    }

    #[Route('/team/{id}/delete', name: 'app_todo_team_user_delete')]
    public function teamUserDelete(Request $request, ManagerRegistry $doctrine, string $id): Response
    {
        $teamId = $request->query->get('id');
        $from = $request->query->get('from');
        $team = $doctrine->getRepository(Team::class)->find($teamId);
        if ($team === null) {
            throw $this->createNotFoundException('Team not found');
        }
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $team->removeUser($user);
        $doctrine->getManager()->flush();
        return $this->redirectToRoute($from, ['team' => $teamId]);
    }

    #[Route('/me', name: 'app_todo_user', methods: ['GET'])]
    public function userHome(ManagerRegistry $doctrine): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
        }
        $data = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'creation_date' => $user->getCreationDate()->format('M-d-Y'),
        ];
        return $this->render('user/home.html.twig', [
            'user' => $data,
        ]);
    }

    #[Route('/user/{id}', name: 'app_todo_user_id', methods: ['GET'])]
    public function userById(ManagerRegistry $doctrine, string $id): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
        }
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
        return $this->render('user/home.html.twig', [
            'user' => $data,
        ]);
    }

    #[Route('/user/edit', name: 'app_todo_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(ManagerRegistry $doctrine, Request $request): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
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

    #[Route('/user/delete', name: 'app_todo_user_delete', methods: ['GET', 'POST'])]
    public function userDelete(Request $request, ManagerRegistry $doctrine): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
        }
        $form = $this->createForm(UserDeletionConfirmFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $doctrine->getManager()->remove($user);
            $doctrine->getManager()->flush();
            return $this->redirectToRoute('app_register', ['deleted' => true]);
        }
        return $this->render('user/delete.html.twig', [
            'userDeletionForm' => $form,
        ]);
    }

    #[Route('/user/tasks', name: 'app_todo_user_tasks', methods: ['GET'])]
    public function userTasks(ManagerRegistry $doctrine): JsonResponse
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
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

    #[Route('/user/task/{task_id}', name: 'app_todo_user_task', methods: ['GET'])]
    public function userTaskById(ManagerRegistry $doctrine, string $task_id): JsonResponse
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
        }
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

    #[Route('/tasks/new', name: 'app_todo_user_task_create')]
    public function userTaskCreate(ManagerRegistry $doctrine, Request $request): Response
    {
        if (isset($_COOKIE['loginToken'])) {
            $user = $doctrine->getRepository(User::class)->findOneBy(['password' => $_COOKIE['loginToken']]);
            if ($user === null) {
                $this->redirectToRoute('app_login');
            }
        } else {
            $this->redirectToRoute('app_login');
        }
        $id = $request->query->get('id');
        $user = $doctrine->getRepository(User::class)->find($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }
        $form = $this->createForm(NewTaskFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $description = $form->get('description')->getData();
            $due_date = $form->get('dueDate')->getData();
            $status = $form->get('status')->getData();
            $task = new \App\Entity\Task();
            $task->setName($name);
            $task->setDescription($description);
            $task->setCreationDate(new \DateTime());
            $task->setDueDate($due_date);
            $task->setStatus($status);
            $task->setUser($user);
            $doctrine->getManager()->persist($task);
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Task created successfully');
            return $this->redirectToRoute('app_todo', ['new' => false]);
        }

        return $this->render('task/new.html.twig', [
            'form' => $form->createView(),
        ]);
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

    #[Route('/task/{id}/edit', name: 'app_todo_task_edit')]
    public function taskEdit(ManagerRegistry $doctrine, string $id, Request $request)
    {
        $task = $doctrine->getRepository(Task::class)->find($id);
        if ($task === null) {
            throw $this->createNotFoundException('Task not found');
        }
        $status = $request->query->get('status');
        $from = $request->query->get('from');
        $teamId = $request->query->get('id');
        $task->setStatus($status);
        $doctrine->getManager()->flush();
        return $this->redirectToRoute($from, ['team' => $teamId]);
    }

    #[Route('/task/{id}/delete', name: 'app_todo_task_delete')]
    public function taskDelete(ManagerRegistry $doctrine, string $id, Request $request)
    {
        $task = $doctrine->getRepository(Task::class)->find($id);
        if ($task === null) {
            throw $this->createNotFoundException('Task not found');
        }
        $from = $request->query->get('from');
        $teamId = $request->query->get('id');
        $doctrine->getManager()->remove($task);
        $doctrine->getManager()->flush();
        return $this->redirectToRoute($from, ['team' => $teamId]);
    }
}
