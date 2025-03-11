<?php

// BaseFilterType.php

namespace App\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BaseFilterType extends AbstractType
{
    protected static function mysql_regex_escape($str): string
    {
        return preg_replace('/([\W])/u', '\\\\$1', $str);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'   => false,
            'validation_groups' => ['filtering'], // avoid NotBlank() constraint-related message
        ]);
    }
}
