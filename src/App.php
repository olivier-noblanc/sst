<?php

declare(strict_types=1);

namespace SST;

use PDO;

/**
 * Application entry point — SST DREETS BFC
 *
 * Central class for application-wide services and configuration.
 */
final class App
{
    private static ?PDO $pdo = null;
    private static ?array $config = null;

    /**
     * Get the database connection (singleton).
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = \getDatabase();
        }
        return self::$pdo;
    }

    /**
     * Get a configuration value.
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        return \getConfig($key) ?? $default;
    }

    /**
     * Get the application version from CHANGELOG.md.
     */
    public static function version(): string
    {
        return \getAppVersion();
    }

    /**
     * Get the application name.
     */
    public static function name(): string
    {
        return APP_NAME;
    }
}
