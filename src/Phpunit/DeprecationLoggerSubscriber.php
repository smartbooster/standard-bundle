<?php

declare(strict_types=1);

namespace Smart\StandardBundle\Phpunit;

use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\DeprecationTriggered as TestDeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber as TestDeprecationTriggeredSubscriber;
use PHPUnit\Event\Test\PhpDeprecationTriggered as TestPhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber as TestPhpDeprecationTriggeredSubscriber;
use PHPUnit\Event\Test\PhpunitDeprecationTriggered as TestPhpunitDeprecationTriggered;
use PHPUnit\Event\Test\PhpunitDeprecationTriggeredSubscriber as TestPhpunitDeprecationTriggeredSubscriber;
use PHPUnit\Event\TestRunner\DeprecationTriggered as TestRunnerDeprecationTriggered;
use PHPUnit\Event\TestRunner\DeprecationTriggeredSubscriber as TestRunnerDeprecationTriggeredSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * Subscriber linked to DeprecationLoggerExtension to format deprecations.
 */
final class DeprecationLoggerSubscriber implements
    TestDeprecationTriggeredSubscriber,
    TestPhpDeprecationTriggeredSubscriber,
    TestPhpunitDeprecationTriggeredSubscriber,
    TestRunnerDeprecationTriggeredSubscriber
{
    private ?string $logFile;

    /**
     * @var array<string, array<string, array{
     *     file: string,
     *     line: int,
     *     message: string,
     *     count: int,
     *     tests: array<string|int, array{
     *         file: string|null,
     *         line: int|null
     *     }>
     * }>>
     */
    private array $deprecations = [];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $testsByType = [];
    private bool $printed = false;

    public function __construct(?string $logFile)
    {
        $this->logFile = $logFile;

        if (null !== $this->logFile) {
            register_shutdown_function(function (): void { // phpcs:ignore
                $this->writeSummaryToLog();
            });

            // Catch @trigger_error()
            set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                if (E_USER_DEPRECATED === $errno || E_DEPRECATED === $errno) {
                    $this->recordSuppressedDeprecation('user', $errfile, $errline, $errstr);

                    return true; // Avoid console display
                }

                return false;
            });
        }
    }

    public function notify(object $event): void
    {
        if (null === $this->logFile) {
            return;
        }

        if ($event instanceof TestDeprecationTriggered) {
            $this->recordDeprecation(
                'user',
                $event->file(),
                $event->line(),
                $event->message(),
                $event->test()
            );

            return;
        }

        if ($event instanceof TestPhpDeprecationTriggered) {
            $this->recordDeprecation(
                'php',
                $event->file(),
                $event->line(),
                $event->message(),
                $event->test()
            );

            return;
        }

        if ($event instanceof TestPhpunitDeprecationTriggered) {
            $this->recordTestOnlyDeprecation(
                'phpunit',
                $event->message(),
                $event->test()
            );

            return;
        }

        if ($event instanceof TestRunnerDeprecationTriggered) {
            $this->recordRunnerDeprecation('runner', $event->message());
        }
    }

    private function recordDeprecation(string $type, string $file, int $line, string $message, Test $test): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        /** @var string $file */
        /** @var int $line */
        [$file, $line] = $this->resolveRealFileAndLine($file, $line, $trace);

        $key = hash('sha256', $type . "\n" . $file . "\n" . $line . "\n" . $message);

        if (!isset($this->deprecations[$type][$key])) {
            $this->deprecations[$type][$key] = [
                'file' => $file,
                'line' => $line,
                'message' => $message,
                'count' => 0,
                'tests' => [],
            ];
        }

        ++$this->deprecations[$type][$key]['count'];

        $testId = $test->id();
        $testFile = $test->file();
        $testLine = null;
        if ($test instanceof TestMethod) {
            $testLine = $test->line();
        }

        $this->deprecations[$type][$key]['tests'][$testId] = [
            'file' => $testFile,
            'line' => $testLine,
        ];
        $this->testsByType[$type][$testId] = true;
    }

    private function recordSuppressedDeprecation(string $type, string $file, int $line, string $message): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        /** @var string $file */
        /** @var int $line */
        [$file, $line] = $this->resolveRealFileAndLine($file, $line, $trace);

        $key = hash('sha256', $type . "\n" . $file . "\n" . $line . "\n" . $message);

        if (!isset($this->deprecations[$type][$key])) {
            $this->deprecations[$type][$key] = [
                'file' => $file,
                'line' => $line,
                'message' => $message,
                'count' => 0,
                'tests' => [],
            ];
        }

        ++$this->deprecations[$type][$key]['count'];

        /** @var string $testId */
        /** @var string $testFile */
        /** @var int $testLine */
        [$testId, $testFile, $testLine] = $this->findTestFromTrace($trace);

        $this->deprecations[$type][$key]['tests'][$testId] = [
            'file' => $testFile,
            'line' => $testLine,
        ];
        $this->testsByType[$type][$testId] = true;
    }

    private function recordTestOnlyDeprecation(string $type, string $message, Test $test): void
    {
        $key = hash('sha256', $type . "\n" . $message);

        if (!isset($this->deprecations[$type][$key])) {
            $this->deprecations[$type][$key] = [
                'file' => '[unknown]',
                'line' => 0,
                'message' => $message,
                'count' => 0,
                'tests' => [],
            ];
        }

        ++$this->deprecations[$type][$key]['count'];

        $testId = $test->id();
        $testFile = $test->file();
        $testLine = null;
        if ($test instanceof TestMethod) {
            $testLine = $test->line();
        }

        $this->deprecations[$type][$key]['tests'][$testId] = [
            'file' => $testFile,
            'line' => $testLine,
        ];
        $this->testsByType[$type][$testId] = true;
    }

    private function recordRunnerDeprecation(string $type, string $message): void
    {
        $key = hash('sha256', $type . "\n" . $message);

        if (!isset($this->deprecations[$type][$key])) {
            $this->deprecations[$type][$key] = [
                'file' => '[test-runner]',
                'line' => 0,
                'message' => $message,
                'count' => 0,
                'tests' => [],
            ];
        }

        ++$this->deprecations[$type][$key]['count'];
    }

    /**
     * @param list<array{file?: string, line?: int}> $trace
     *
     * @return array{0: string, 1: int}
     */
    private function resolveRealFileAndLine(string $file, int $line, array $trace): array
    {
        $wrappers = [
            'vendor/doctrine/deprecations',
            'vendor/symfony/deprecation-contracts',
        ];

        foreach ($wrappers as $wrapper) {
            if (str_contains($file, $wrapper)) {
                foreach ($trace as $frame) {
                    if (
                        isset($frame['file'])
                        && !str_contains($frame['file'], 'vendor/doctrine/deprecations')
                        && !str_contains($frame['file'], 'vendor/symfony/deprecation-contracts')
                        && __FILE__ !== $frame['file']
                    ) {
                        return [$frame['file'], $frame['line'] ?? $line];
                    }
                }
                break;
            }
        }

        return [$file, $line];
    }

    /**
     * @param list<array{class?: string, function?: string, file?: string, line?: int}> $trace
     *
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    private function findTestFromTrace(array $trace): array
    {
        $testId = '[kernel / boot]';
        $testFile = null;
        $testLine = null;

        foreach ($trace as $frame) {
            if (isset($frame['class']) && is_subclass_of($frame['class'], TestCase::class)) {
                $testId = $frame['class'] . '::' . ($frame['function'] ?? '');
                $testFile = $frame['file'] ?? null;
                $testLine = $frame['line'] ?? null;
                break;
            }
        }

        return [$testId, $testFile, $testLine];
    }

    private function writeSummaryToLog(): void
    {
        if ($this->printed || null === $this->logFile) {
            return;
        }

        $types = [
            'php' => 'PHP',
            'user' => 'user',
            'phpunit' => 'phpunit',
            'runner' => 'test runner',
        ];

        $output = '';

        foreach ($types as $type => $label) {
            if (empty($this->deprecations[$type])) {
                continue;
            }

            $totalEvents = 0;
            foreach ($this->deprecations[$type] as $data) {
                $totalEvents += $data['count'];
            }

            $testCount = isset($this->testsByType[$type]) ? count($this->testsByType[$type]) : 0;

            if ('' === $output) {
                $output .= $this->buildHeader();
            } else {
                $output .= "\n";
            }

            $output .= sprintf(
                "%d tests triggered %d %s deprecations:\n\n",
                $testCount,
                $totalEvents,
                $label
            );

            $index = 1;
            foreach ($this->deprecations[$type] as $data) {
                $output .= sprintf(
                    "%d) %s:%d\n%s\n\nTriggered by:\n\n",
                    $index,
                    $data['file'],
                    $data['line'],
                    $data['message']
                );

                foreach ($data['tests'] as $testId => $testInfo) {
                    $output .= '* ' . $testId . "\n";
                    if (!empty($testInfo['file'])) {
                        $output .= '  ' . $testInfo['file'];
                        if (null !== $testInfo['line']) {
                            $output .= ':' . $testInfo['line'];
                        }
                        $output .= "\n";
                    }
                }

                if (empty($data['tests'])) {
                    $output .= "* test runner\n";
                }

                $output .= "\n";
                ++$index;
            }
        }

        if ('' === $output) {
            $output = $this->buildHeader() . "No deprecations.\n";
        }

        $this->printed = true;
        @file_put_contents($this->logFile, $output, LOCK_EX);
    }

    private function buildHeader(): string
    {
        $memoryText = $this->formatBytes(memory_get_peak_usage(true));

        return sprintf("Memory: %s\n\n", $memoryText);
    }

    private function formatBytes(int $bytes): string
    {
        $mb = $bytes / 1024 / 1024;

        return sprintf('%.2f MB', $mb);
    }
}
