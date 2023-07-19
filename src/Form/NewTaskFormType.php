<?php

namespace App\Form;

use App\Entity\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;


class NewTaskFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Task Name',
                'label_attr' => [
                    'class' => 'sr-only'
                ],
                'attr' => [
                    'placeholder' => 'Task Name',
                    'class' => 'w-full rounded-lg border-gray-200 p-3 text-sm',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Task Description',
                'label_attr' => [
                    'class' => 'sr-only'
                ],
                'attr' => [
                    'placeholder' => 'Task Description',
                    'class' => 'w-full rounded-lg border-gray-200 p-3 text-sm',
                    'rows' => '8',
                ],
            ])
            ->add('dueDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Due Date',
                'label_attr' => [
                    'class' => 'sr-only'
                ],
                'attr' => [
                    'placeholder' => 'Due Date',
                    'class' => 'w-full rounded-lg border-gray-200 p-3 text-sm',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Not Started' => 'Not Started',
                    'In Progress' => 'In Progress',
                    'Completed' => 'Completed',
                ],
                'multiple' => false,
                'expanded' => true,
                'attr' => [
                    'class' => 'peer sr-only',
                ],
            ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
        ]);
    }
}