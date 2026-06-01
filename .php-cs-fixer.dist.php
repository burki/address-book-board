<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->notPath('tests/bootstrap.php')
    ->exclude([
        'vendor',
        'var',
    ])
    ->notPath([
        'config/bundles.php',
        'config/preload.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PER-CS' => true,
        '@PHP82Migration' => true,
        'control_structure_continuation_position' => ['position' => 'next_line'],
        'elseif' => false, // don't change else if to elseif
    ])
    ->setFinder($finder)
;
