<?php

/**
 * Configuration for PyroCMS SSTI Fix (CVE-2023-29689)
 *
 * This package mitigates the Server-Side Template Injection vulnerability
 * in PyroCMS by applying Twig's sandbox to user-editable templates.
 *
 * Default settings are secure and require no modification for most installations.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Fix
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable the fix. Set to false to completely
    | disable sandbox injection (not recommended in production).
    |
    */
    'enabled' => env('PYROCMS_SSTI_FIX_ENABLED', true),

    // There is no purpose to having the mode setting on this repo where using auto is the whole point, keeping it (commented out) only for the sake of reference since I may split out some of the code into its own "improved twig sandbox" repo later.
    // /*
    // |--------------------------------------------------------------------------
    // | Sandbox Mode
    // |--------------------------------------------------------------------------
    // |
    // | Determines how the sandbox is applied:
    // |
    // | - 'auto'   : (Recommended) Sandbox is selectively enabled based on template
    // |              source. Templates from storage/database are sandboxed; filesystem
    // |              templates from themes/addons are not.
    // |
    // | - 'global' : Sandbox applies to ALL templates. May break legitimate
    // |              functionality in themes that use advanced Twig features.
    // |
    // | - 'manual' : Sandbox is loaded but disabled. Must be explicitly enabled
    // |              using {% sandbox %} tag or include(sandbox=true).
    // |
    // */
    // 'mode' => env('PYROCMS_SSTI_FIX_MODE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path where user-editable templates are stored. Templates loaded from
    | paths starting with this value will be sandboxed (in 'auto' mode).
    |
    | Leave null to auto-detect from PyroCMS Application or fall back to
    | Laravel's storage_path().
    |
    */
    'storage_path' => env('PYROCMS_SSTI_FIX_STORAGE_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | Source Policy Class
    |--------------------------------------------------------------------------
    |
    | The class that determines WHEN to apply the sandbox. The default
    | StorageSourcePolicy sandboxes templates from the storage path.
    |
    | You can specify a custom class that implements SourcePolicyInterface
    | for more complex logic.
    |
    */
    'source_policy' => \YSaxon\PyroCmsSstiFix\StorageSourcePolicy::class,

    /*
    |--------------------------------------------------------------------------
    | Security Policy (What's Allowed)
    |--------------------------------------------------------------------------
    |
    | Defines what Twig features are allowed in sandboxed templates.
    |
    | Use the special value SecurityPolicyDefaults::INCLUDE_DEFAULTS to include
    | the secure defaults, then add custom items as needed.
    |
    | SECURITY NOTE: The defaults deliberately EXCLUDE dangerous filters like
    | 'map', 'filter', 'reduce', and 'sort' (with callable) which can be used
    | for RCE via payloads like: {{['id']|map('system')}}
    |
    */
    'policy' => [

        // Allowed Twig tags
        // Default excludes: include, extends, block, macro, import, embed, use
        // (These could allow template inclusion attacks if enabled)
        'tags' => [
            \YSaxon\PyroCmsSstiFix\SecurityPolicyDefaults::INCLUDE_DEFAULTS,
            // Add custom tags here if needed:
            // 'custom_tag',
        ],

        // Allowed Twig filters
        // Default excludes: map, filter, reduce (RCE vectors)
        'filters' => [
            \YSaxon\PyroCmsSstiFix\SecurityPolicyDefaults::INCLUDE_DEFAULTS,
            // Add custom filters here if needed:
            // 'custom_filter',
        ],

        // Allowed Twig functions
        'functions' => [
            \YSaxon\PyroCmsSstiFix\SecurityPolicyDefaults::INCLUDE_DEFAULTS,
            // Add custom functions here if needed:
            // 'custom_function',
        ],

        // Allowed object methods (format: 'ClassName' => ['method1', 'method2'])
        // Supports wildcards: 'get*' matches getMethods, getProperty, etc.
        // Supports '*' for class to match any class
        'methods' => [
            \YSaxon\PyroCmsSstiFix\SecurityPolicyDefaults::INCLUDE_DEFAULTS,
            // Add custom methods here if needed:
            // 'App\Models\Post' => ['getTitle', 'getContent'],
        ],

        // Allowed object properties (format: 'ClassName' => ['prop1', 'prop2'])
        'properties' => [
            \YSaxon\PyroCmsSstiFix\SecurityPolicyDefaults::INCLUDE_DEFAULTS,
            // Add custom properties here if needed:
            // 'App\Models\Post' => ['title', 'content'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, logs debug information about sandbox application.
    | Disable in production.
    |
    */
    'debug' => env('PYROCMS_SSTI_FIX_DEBUG', false),

];
