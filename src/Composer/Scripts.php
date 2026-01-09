<?php

namespace YSaxon\PyroCmsSstiFix\Composer;

use Composer\Script\Event;

final class Scripts
{
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();

        // Check if provider is already configured
        if (self::isProviderRegistered()) {
            $io->write('<info>PyroCMS SSTI Fix installed and already registered.</info>');
            return;
        }

        $io->write('');
        $io->write('<info>PyroCMS SSTI Fix installed.</info>');
        $io->write('<comment>PyroCMS disables package autodiscovery.</comment>');
        $io->write('<comment>Manually add this ServiceProvider to config/app.php:</comment>');
        $io->write('<comment>  YSaxon\\PyroCmsSstiFix\\SandboxServiceProvider::class</comment>');
        $io->write('');
    }

    private static function isProviderRegistered(): bool
    {
        // Try to find the root project's config/app.php
        // This works because the package is in vendor/, so config/app.php is 5 levels up
        $configPath = __DIR__ . '/../../../../../config/app.php';

        if (!file_exists($configPath)) {
            return false;
        }

        try {
            $config = include $configPath;

            if (is_array($config) && isset($config['providers'])) {
                $providerClass = 'YSaxon\\PyroCmsSstiFix\\SandboxServiceProvider';
                return in_array($providerClass, $config['providers'], true);
            }
        } catch (\Throwable $e) {
            // If we can't read/parse the config, assume it's not registered
            return false;
        }

        return false;
    }
}
