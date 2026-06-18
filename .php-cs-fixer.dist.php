<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/handlers',
        __DIR__ . '/pages',
        __DIR__ . '/public',
    ])
    ->exclude([
        'lib',          // src/lib — third-party (Parsedown, FPDF)
        'node_modules',
    ])
    ->notPath('*.min.js')
    ->notPath('*.min.css');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => false, // Keep PHPStan types
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/data/.php-cs-fixer.cache');
