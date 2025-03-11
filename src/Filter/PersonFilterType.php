<?php

// PersonFilterType.php
namespace App\Filter;

use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use Symfony\Component\Form\FormBuilderInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class PersonFilterType extends BaseFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /*
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
        */

        $builder->add('fulltext', Filters\TextFilterType::class, [
            'label' => 'Suche',
            'apply_filter' => static function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return;
                }

                $fields = [
                    'p.nameFull' => 'p.nameFull',
                    'address1926' => 'JSON_UNQUOTE(JSON_EXTRACT(p.infoByYear, \'$."1926".address\'))',
                ];

                $parameters = [];

                $words = preg_split('/\,?\s+/', trim($values['value']));
                $andParts = [];
                foreach ($words as $i => $word) {
                    if (empty($word)) {
                        continue;
                    }

                    // expression parameters
                    $paramName = sprintf('p_fulltext_%d', $i);
                    $parameters[$paramName] = self::mysql_regex_escape($word);

                    $orParts = [];
                    foreach ($fields as $fieldName => $expr) {
                        // expression that represents the condition
                        $orParts[] = 'REGEXP(LOWER(' . $expr . '), LOWER(:' . $paramName . ')) = true';
                    }

                    $andParts[] = '(' . implode(' OR ', $orParts) . ')';
                }

                $expression = $filterQuery->getExpr()
                    ->andX(join(' AND ', $andParts));

                return $filterQuery->createCondition($expression, $parameters);
            }
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'person_filter';
    }
}
