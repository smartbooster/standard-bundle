<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->append([
        'bin/console',
    ])
    ->path([
        'bin/',
        'config/',
        'public/',
        'src/',
        'tests/',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'class_definition' => [
            'multi_line_extends_each_single_line' => true, # https://cs.symfony.com/doc/rules/class_notation/class_definition.html#example-4
        ],
        'concat_space' => ['spacing' => 'one'], # the @PER like PSR12 preserve space
    ])
    ->setFinder($finder)
;
