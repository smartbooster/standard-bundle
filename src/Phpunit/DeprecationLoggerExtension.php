<?php

declare(strict_types=1);

namespace Smart\StandardBundle\Phpunit;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension to log deprecations not caught by native PHPUnit.
 */
final class DeprecationLoggerExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $logFile = $this->resolveLogFile($parameters);

        if (null === $logFile) {
            return;
        }

        $logDir = \dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0o777, true);
        }

        $facade->registerSubscriber(new DeprecationLoggerSubscriber($logFile));
    }

    private function resolveLogFile(ParameterCollection $parameters): ?string
    {
        $path = $_SERVER['DEPRECATIONS_LOG_FILE']
            ?? $_ENV['DEPRECATIONS_LOG_FILE']
            ?? getenv('DEPRECATIONS_LOG_FILE')
            ?: null;

        if (!$path || !is_string($path)) {
            return null;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $projectRoot = getcwd() ?: \dirname(__DIR__, 3);

        return $projectRoot . DIRECTORY_SEPARATOR . $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ('' === $path) {
            return false;
        }

        if ('/' === $path[0] || '\\' === $path[0]) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
