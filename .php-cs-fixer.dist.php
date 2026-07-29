<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        // ── Base ──────────────────────────────────────
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // ── PHP 8.4 ───────────────────────────────────
        '@PHP84Migration' => true,
        '@PHP80Migration:risky' => true,

        // ── Type hints ────────────────────────────────
        'declare_strict_types' => true,
        'phpdoc_to_property_type' => true,
        'phpdoc_to_return_type' => true,
        'phpdoc_to_param_type' => true,

        // ── Imports ───────────────────────────────────
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // ── Arrays y sintaxis moderna ─────────────────
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => true,

        // ── Modernización ─────────────────────────────
        'nullable_type_declaration' => ['syntax' => 'union'],
        'nullable_type_declaration_for_default_null_value' => true,
        'no_superfluous_phpdoc_tags' => ['remove_inheritdoc' => true],
        'void_return' => true,

        // ── Overrides PSR-12 ──────────────────────────
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
;
