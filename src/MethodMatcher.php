<?php

namespace YSaxon\PyroSstiHotfix;

/**
 * Efficient matcher for method/property allowlists with wildcard support.
 *
 * Supports patterns like:
 * - 'methodName' - Exact match
 * - 'get*' - Prefix wildcard (matches getMethods, getProperty, etc.)
 * - '*' - Match any method
 *
 * Class matching supports:
 * - 'ClassName' - Exact class or instanceof match
 * - '*' - Match any class
 *
 * Results are cached for performance.
 *
 * @author Yaakov Saxon
 */
class MethodMatcher
{
    /**
     * Allowed methods/properties indexed by class.
     */
    protected array $allowed;

    /**
     * Cache of match results.
     */
    protected array $cache = [];

    /**
     * Whether method/property matching is case-insensitive.
     */
    protected bool $caseInsensitive;

    /**
     * Create a new MethodMatcher instance.
     *
     * @param array $allowed Array of ['ClassName' => ['method1', 'method2', ...], ...]
     * @param bool $caseInsensitive Whether to perform case-insensitive matching (default: true)
     */
    public function __construct(array $allowed, bool $caseInsensitive = true)
    {
        $this->caseInsensitive = $caseInsensitive;

        // Normalize method names to lowercase if case-insensitive matching is enabled
        $this->allowed = [];
        foreach ($allowed as $class => $methods) {
            $normalizedMethods = [];
            foreach ((array) $methods as $method) {
                $normalizedMethods[] = $caseInsensitive ? strtolower($method) : $method;
            }
            $this->allowed[$class] = $normalizedMethods;
        }
    }

    /**
     * Check if a method/property is allowed on the given object.
     *
     * @param object $obj The object instance
     * @param string $method The method/property name
     * @return bool True if allowed
     */
    public function isAllowed(object $obj, string $method): bool
    {
        $class = get_class($obj);
        $methodLower = $this->caseInsensitive ? strtolower($method) : $method;
        $cacheKey = $class . '::' . $methodLower;

        // Check cache
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = $this->checkAllowed($obj, $class, $methodLower);

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Perform the actual allowed check.
     *
     * @param object $obj The object instance
     * @param string $class The object's class name
     * @param string $method The method name (lowercase)
     * @return bool True if allowed
     */
    protected function checkAllowed(object $obj, string $class, string $method): bool
    {
        foreach ($this->allowed as $allowedClass => $allowedMethods) {
            // Check if class matches
            if (!$this->classMatches($obj, $class, $allowedClass)) {
                continue;
            }

            // Check if method matches
            if ($this->methodMatches($method, $allowedMethods)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a class matches the allowed class pattern.
     *
     * @param object $obj The object instance
     * @param string $class The object's class name
     * @param string $allowedClass The allowed class pattern
     * @return bool True if matches
     */
    protected function classMatches(object $obj, string $class, string $allowedClass): bool
    {
        // Wildcard matches any class
        if ($allowedClass === '*') {
            return true;
        }

        // Normalize class names (handle leading backslash)
        $normalizedAllowed = ltrim($allowedClass, '\\');
        $normalizedClass = ltrim($class, '\\');

        // Exact match
        if ($normalizedClass === $normalizedAllowed) {
            return true;
        }

        // instanceof check (handles interfaces and parent classes)
        if ($obj instanceof $allowedClass) {
            return true;
        }

        // Also try with leading backslash
        // if ($obj instanceof ('\\' . $normalizedAllowed)) {
        $classWithSlash = '\\' . $normalizedAllowed;
        if ($obj instanceof $classWithSlash) {
            return true;
        }

        return false;
    }

    /**
     * Check if a method matches any of the allowed patterns.
     *
     * @param string $method The method name (lowercase)
     * @param array $allowedMethods Array of allowed method patterns
     * @return bool True if matches
     */
    protected function methodMatches(string $method, array $allowedMethods): bool
    {
        foreach ($allowedMethods as $pattern) {
            // Wildcard matches any method
            if ($pattern === '*') {
                return true;
            }

            // Exact match
            if ($pattern === $method) {
                return true;
            }

            // Prefix wildcard (e.g., 'get*')
            // if (str_ends_with($pattern, '*')) {
            if (substr($pattern, -1) === '*') {
                $prefix = substr($pattern, 0, -1);
                // if (str_starts_with($method, $prefix)) {
                if (substr($method, 0, strlen($prefix)) === $prefix) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clear the match cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
