<?php

// CompanyFilterType.php
namespace App\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class CompanyFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Filters\TextFilterType::class);
    }

    public function getBlockPrefix(): string
    {
        return 'company_filter';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'   => false,
            'validation_groups' => array('filtering') // avoid NotBlank() constraint-related message
        ]);
    }
}
