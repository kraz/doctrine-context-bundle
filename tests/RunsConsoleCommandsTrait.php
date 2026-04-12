<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function sys_get_temp_dir;
use function unlink;

trait RunsConsoleCommandsTrait
{
    private Application $application;

    private function runCommand(string $command): int
    {
        return $this->application->run(new StringInput($command), new BufferedOutput());
    }

    /**
     * Runs a command and returns its output as a string.
     *
     * PHPUnit sets SHELL_VERBOSITY=-1 to keep its own output clean, which causes
     * Symfony's Application to silence all command output. We temporarily override
     * that so the captured output reflects what a real terminal would see.
     *
     * Symfony's Application::configureIO reads $_ENV first, then $_SERVER, then getenv().
     * PHPUnit sets $_SERVER['SHELL_VERBOSITY']=-1 via phpunit.xml.dist; overriding $_ENV
     * is sufficient to prevent the quiet-mode override.
     */
    private function captureOutput(string $command): string
    {
        $previousEnv    = $_ENV['SHELL_VERBOSITY'] ?? null;
        $previousServer = $_SERVER['SHELL_VERBOSITY'] ?? null;

        $_ENV['SHELL_VERBOSITY']    = 0;
        $_SERVER['SHELL_VERBOSITY'] = 0;

        try {
            $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
            $this->application->run(new StringInput($command), $output);

            return $output->fetch();
        } finally {
            if ($previousEnv !== null) {
                $_ENV['SHELL_VERBOSITY'] = $previousEnv;
            } else {
                unset($_ENV['SHELL_VERBOSITY']);
            }

            if ($previousServer !== null) {
                $_SERVER['SHELL_VERBOSITY'] = $previousServer;
            } else {
                unset($_SERVER['SHELL_VERBOSITY']);
            }
        }
    }

    private function cleanDatabases(): void
    {
        foreach (['default', 'alpha', 'beta'] as $name) {
            $path = sys_get_temp_dir() . '/' . $this->databaseFilePrefix() . $name . '.db';
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_';
    }
}
