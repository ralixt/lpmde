<?php

namespace App\Form;

use App\Entity\TrocAnnonce;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrocAnnonceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'annonce',
                'attr' => [
                    'placeholder' => 'Ex: Figurine Cthulhu édition limitée',
                    'class' => 'w-full bg-surface-dark border border-slate-700 rounded px-4 py-2 text-white focus:border-primary focus:outline-none',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Décrivez l\'objet, son état, ce que vous recherchez en échange...',
                    'rows' => 5,
                    'class' => 'w-full bg-surface-dark border border-slate-700 rounded px-4 py-2 text-white focus:border-primary focus:outline-none',
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Figurines' => 'Figurines',
                    'Films' => 'Films',
                    'Jeux' => 'Jeux',
                    'BD & Livres' => 'BD & Livres',
                    'Fanzines' => 'Fanzines',
                ],
                'attr' => [
                    'class' => 'w-full bg-surface-dark border border-slate-700 rounded px-4 py-2 text-white focus:border-primary focus:outline-none',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Échange' => 'exchange',
                    'Don' => 'gift',
                ],
                'attr' => [
                    'class' => 'w-full bg-surface-dark border border-slate-700 rounded px-4 py-2 text-white focus:border-primary focus:outline-none',
                ],
            ])
            ->add('condition', ChoiceType::class, [
                'label' => 'État',
                'choices' => [
                    'Neuf' => 'neuf',
                    'Très bon' => 'très bon',
                    'Bon' => 'bon',
                    'Correct' => 'correct',
                ],
                'attr' => [
                    'class' => 'w-full bg-surface-dark border border-slate-700 rounded px-4 py-2 text-white focus:border-primary focus:outline-none',
                ],
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'URL de l\'image (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://...',
                    'class' => 'w-full bg-surface-dark border border-slate-700 rounded px-4 py-2 text-white focus:border-primary focus:outline-none',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrocAnnonce::class,
        ]);
    }
}
