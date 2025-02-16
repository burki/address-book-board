<?php

// PersonFilterType.php
namespace App\Filter;

use Symfony\Component\Form\FormBuilderInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class PersonFilterType extends BaseFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nameFull', Filters\TextFilterType::class, [
            'label' => 'Name',
            'apply_filter' => static function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represents the condition
                $expression = $filterQuery->getExpr()
                    ->andX('REGEXP(LOWER(' . $field.'), LOWER(:'.$paramName.')) = true');

                // expression parameters
                $parameters = [$paramName => self::mysql_regex_escape($values['value'])];

                return $filterQuery->createCondition($expression, $parameters);
            }
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'person_filter';
    }
}
