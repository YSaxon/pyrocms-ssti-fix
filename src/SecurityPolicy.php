<?php

namespace YSaxon\PyroSstiHotfix;

use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Enhanced Twig security policy with wildcard support and sensible defaults.
 * 
 * Improvements over Twig's built-in SecurityPolicy:
 * - Supports INCLUDE_DEFAULTS token to merge custom allowlists with secure defaults
 * - Supports wildcards for methods/properties (e.g., 'get*' matches all getters)
 * - Caches method/property checks for better performance
 * 
 * @author Yaakov Saxon
 */
final class SecurityPolicy implements SecurityPolicyInterface
{
    /**
     * Allowed tags (flipped for O(1) lookup).
     */
    private array $allowedTags;

    /**
     * Allowed filters (flipped for O(1) lookup).
     */
    private array $allowedFilters;

    /**
     * Allowed functions (flipped for O(1) lookup).
     */
    private array $allowedFunctions;

    /**
     * Allowed methods matcher.
     */
    private MethodMatcher $allowedMethods;

    /**
     * Allowed properties matcher.
     */
    private MethodMatcher $allowedProperties;

    /**
     * Create a new SecurityPolicy instance.
     * 
     * For all parameters, you may include SecurityPolicyDefaults::INCLUDE_DEFAULTS
     * to merge with the secure defaults.
     *
     * @param array $allowedTags Tags allowed in sandboxed templates
     * @param array $allowedFilters Filters allowed in sandboxed templates
     * @param array $allowedMethods Methods allowed ['ClassName' => ['method1', ...]]
     * @param array $allowedProperties Properties allowed ['ClassName' => ['prop1', ...]]
     * @param array $allowedFunctions Functions allowed in sandboxed templates
     */
    public function __construct(
        array $allowedTags = [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
        array $allowedFilters = [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
        array $allowedMethods = [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
        array $allowedProperties = [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
        array $allowedFunctions = [SecurityPolicyDefaults::INCLUDE_DEFAULTS]
    ) {
        // Process defaults tokens
        SecurityPolicyDefaults::addDefaultsToAll(
            $allowedTags,
            $allowedFilters,
            $allowedFunctions,
            $allowedMethods,
            $allowedProperties
        );

        // Flip arrays for O(1) lookup
        $this->allowedTags = array_flip($allowedTags);
        $this->allowedFilters = array_flip($allowedFilters);
        $this->allowedFunctions = array_flip($allowedFunctions);

        // Use MethodMatcher for wildcard support
        $this->allowedMethods = new MethodMatcher($allowedMethods,true);
        $this->allowedProperties = new MethodMatcher($allowedProperties,false); // Properties are case-sensitive
    }

    /**
     * Check if the given tags, filters, and functions are allowed.
     *
     * @param string[] $tags Tags used in the template
     * @param string[] $filters Filters used in the template
     * @param string[] $functions Functions used in the template
     * @throws SecurityNotAllowedTagError
     * @throws SecurityNotAllowedFilterError
     * @throws SecurityNotAllowedFunctionError
     */
    public function checkSecurity($tags, $filters, $functions): void
    {
        // Check tags (skip if wildcard allowed)
        if ($tags && !isset($this->allowedTags['*'])) {
            foreach ($tags as $tag) {
                if (!isset($this->allowedTags[$tag])) {
                    throw new SecurityNotAllowedTagError(
                        sprintf('Tag "%s" is not allowed.', $tag),
                        $tag
                    );
                }
            }
        }

        // Check filters (skip if wildcard allowed)
        if ($filters && !isset($this->allowedFilters['*'])) {
            foreach ($filters as $filter) {
                if (!isset($this->allowedFilters[$filter])) {
                    throw new SecurityNotAllowedFilterError(
                        sprintf('Filter "%s" is not allowed.', $filter),
                        $filter
                    );
                }
            }
        }

        // Check functions (skip if wildcard allowed)
        if ($functions && !isset($this->allowedFunctions['*'])) {
            foreach ($functions as $function) {
                if (!isset($this->allowedFunctions[$function])) {
                    throw new SecurityNotAllowedFunctionError(
                        sprintf('Function "%s" is not allowed.', $function),
                        $function
                    );
                }
            }
        }
    }

    /**
     * Check if a method call is allowed on the given object.
     *
     * @param object $obj The object instance
     * @param string $method The method name
     * @throws SecurityNotAllowedMethodError
     */
    public function checkMethodAllowed($obj, $method): void
    {
        if (!$this->allowedMethods->isAllowed($obj, $method)) {
            $class = get_class($obj);
            throw new SecurityNotAllowedMethodError(
                sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, $class),
                $class,
                $method
            );
        }
    }

    /**
     * Check if a property access is allowed on the given object.
     *
     * @param object $obj The object instance
     * @param string $property The property name
     * @throws SecurityNotAllowedPropertyError
     */
    public function checkPropertyAllowed($obj, $property): void
    {
        if (!$this->allowedProperties->isAllowed($obj, $property)) {
            $class = get_class($obj);
            throw new SecurityNotAllowedPropertyError(
                sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, $class),
                $class,
                $property
            );
        }
    }

    // -------------------------------------------------------------------------
    // Setter methods for runtime modification (drop-in compatibility with Twig)
    // -------------------------------------------------------------------------

    /**
     * Set allowed tags.
     *
     * @param array $tags
     */
    public function setAllowedTags(array $tags): void
    {
        $tags = SecurityPolicyDefaults::addDefaultsToIndexedArray($tags, SecurityPolicyDefaults::TAGS);
        $this->allowedTags = array_flip($tags);
    }

    /**
     * Set allowed filters.
     *
     * @param array $filters
     */
    public function setAllowedFilters(array $filters): void
    {
        $filters = SecurityPolicyDefaults::addDefaultsToIndexedArray($filters, SecurityPolicyDefaults::FILTERS);
        $this->allowedFilters = array_flip($filters);
    }

    /**
     * Set allowed functions.
     *
     * @param array $functions
     */
    public function setAllowedFunctions(array $functions): void
    {
        $functions = SecurityPolicyDefaults::addDefaultsToIndexedArray($functions, SecurityPolicyDefaults::FUNCTIONS);
        $this->allowedFunctions = array_flip($functions);
    }

    /**
     * Set allowed methods.
     *
     * @param array $methods
     */
    public function setAllowedMethods(array $methods): void
    {
        $methods = SecurityPolicyDefaults::addDefaultsToAssociativeArray($methods, SecurityPolicyDefaults::METHODS);
        $this->allowedMethods = new MethodMatcher($methods, true);
    }

    /**
     * Set allowed properties.
     *
     * @param array $properties
     */
    public function setAllowedProperties(array $properties): void
    {
        $properties = SecurityPolicyDefaults::addDefaultsToAssociativeArray($properties, SecurityPolicyDefaults::PROPERTIES);
        $this->allowedProperties = new MethodMatcher($properties, false); // Properties are case-sensitive
    }
}
