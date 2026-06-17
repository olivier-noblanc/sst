<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/handlers',
        __DIR__ . '/pages',
        __DIR__ . '/templates',
        __DIR__ . '/public',
    ])
    ->exclude([
        __DIR__ . '/src/lib',
        __DIR__ . '/node_modules',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => false, // Keep PHPStan types
        'phpdoc_to_param_type' => false, // Don't auto-promote (we use PHPDoc for PHPStan)
        'phpdoc_to_return_type' => false,
        'strict_param' => true,
        'strict_comparison' => true,
        'declare_strict_types' => false, // Would break existing code
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/data/.php-cs-fixer.cache');
