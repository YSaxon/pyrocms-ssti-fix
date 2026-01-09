<?php

namespace YSaxon\PyroSstiHotfix;

use Illuminate\Support\ServiceProvider;
use Twig\Environment;
use Twig\Extension\SandboxExtension;

/**
 * Laravel service provider that automatically applies Twig sandbox protection
 * to PyroCMS installations, mitigating CVE-2023-29689 (SSTI -> RCE).
 *
 * This provider auto-discovers via Laravel's package discovery. No configuration
 * required for default behavior (sandbox user-editable templates from storage path).
 *
 * @author Yaakov Saxon
 */
class HotfixServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pyro-ssti-hotfix.php', 'pyro-ssti-hotfix');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
         if (config('pyro-ssti-hotfix.debug', false)) {
        \Log::info('[PyroSstiHotfix] Boot called');
        }

        // Publish config for customization
        $this->publishes([
            __DIR__ . '/../config/pyro-ssti-hotfix.php' => config_path('pyro-ssti-hotfix.php'),
        ], 'pyro-ssti-hotfix-config');

        // Only apply if enabled
        if (!config('pyro-ssti-hotfix.enabled', true)) {
            return;
        }

        // Hook into Twig after it's fully booted
        $this->app->booted(function () {
            $this->applySandbox();
        });
    }

    /**
     * Apply the sandbox extension to the Twig environment.
     */
    protected function applySandbox(): void
    {
        // Check if Twig is bound in the container
        if (!$this->app->bound('twig')) {
            // Twig not registered - maybe not a PyroCMS install or streams-platform not loaded
            if (config('pyro-ssti-hotfix.debug', false)) {
                \Log::warning('[PyroSstiHotfix] Twig not found in container. Sandbox not applied.');
            }
            return;
        }

        $this->app->extend('twig', function (Environment $twig, $app) {
            // Don't double-register
            if ($twig->hasExtension(SandboxExtension::class)) {
                if (config('pyro-ssti-hotfix.debug', false)) {
                    \Log::info('[PyroSstiHotfix] SandboxExtension already registered. Skipping.');
                }
                return $twig;
            }

            // Build the security policy
            $securityPolicy = $this->buildSecurityPolicy();

            // Build the source policy (determines WHEN to sandbox)
            $sourcePolicy = $this->buildSourcePolicy($app);

            // Determine global sandbox mode
            $mode = config('pyro-ssti-hotfix.mode', 'auto');
            $globalSandbox = ($mode === 'global');

            // Create and register the extension
            $sandbox = new SandboxExtension(
                $securityPolicy,
                $globalSandbox,
                ($mode === 'auto') ? $sourcePolicy : null
            );

            $twig->addExtension($sandbox);

            if (config('pyro-ssti-hotfix.debug', false)) {
                \Log::info('[PyroSstiHotfix] Sandbox applied successfully.', [
                    'mode' => $mode,
                    'global' => $globalSandbox,
                ]);
            }

            return $twig;
        });
    }

    /**
     * Build the security policy that defines WHAT is allowed in sandboxed templates.
     */
    protected function buildSecurityPolicy(): SecurityPolicy
    {
        $config = config('pyro-ssti-hotfix.policy', []);

        return new SecurityPolicy(
            $config['tags'] ?? [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
            $config['filters'] ?? [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
            $config['methods'] ?? [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
            $config['properties'] ?? [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
            $config['functions'] ?? [SecurityPolicyDefaults::INCLUDE_DEFAULTS]
        );
    }

    /**
     * Build the source policy that determines WHEN to apply the sandbox.
     */
    protected function buildSourcePolicy($app): \Twig\Sandbox\SourcePolicyInterface
    {
        $policyClass = config('pyro-ssti-hotfix.source_policy', StorageSourcePolicy::class);

        // If it's our default policy, we need to resolve the storage path
        if ($policyClass === StorageSourcePolicy::class) {
            return new StorageSourcePolicy($this->resolveStoragePath($app));
        }

        // Custom policy class - let the container resolve it
        return $app->make($policyClass);
    }

    /**
     * Resolve the storage path where user-editable templates live.
     */
    protected function resolveStoragePath($app): string
    {
        // Try to get from config first
        $configPath = config('pyro-ssti-hotfix.storage_path');
        if ($configPath) {
            return $configPath;
        }

        // Try PyroCMS Application class
        if ($app->bound('Anomaly\Streams\Platform\Application\Application')) {
            $pyroApp = $app->make('Anomaly\Streams\Platform\Application\Application');
            if (method_exists($pyroApp, 'getStoragePath')) {
                return $pyroApp->getStoragePath();
            }
        }

        // Fallback to Laravel's storage path
        return storage_path();
    }
}
