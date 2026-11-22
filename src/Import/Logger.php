<?php

namespace WBPI\Import;

use WBPI\Core\Helpers;

defined('ABSPATH') || exit;

class Logger
{
    private string $logFile;

    public function __construct()
    {
        $this->logFile = WBPI_PATH . 'logs/import-errors.log';

        if (!is_dir(dirname($this->logFile))) {
            wp_mkdir_p(dirname($this->logFile));
        }

        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function fatal(string $message, array $context = []): void
    {
        $this->write('FATAL', $message, $context);
    }

    public function clear(): bool
    {
        return file_put_contents($this->logFile, '') !== false;
    }

    public function exists(): bool
    {
        return file_exists($this->logFile);
    }

    public function path(): string
    {
        return $this->logFile;
    }

    public function size(): int
    {
        if (!$this->exists()) {
            return 0;
        }

        return filesize($this->logFile);
    }

    public function read(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_reverse($lines ?: []);
    }

    public function stats(): array
    {
        $content = '';

        if ($this->exists()) {
            $content = file_get_contents($this->logFile);
        }

        return [
            'total'   => substr_count($content, '['),
            'info'    => preg_match_all('/INFO/i', $content),
            'warning' => preg_match_all('/WARNING/i', $content),
            'error'   => preg_match_all('/ERROR/i', $content),
            'fatal'   => preg_match_all('/FATAL/i', $content),
        ];
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s",
            Helpers::now(),
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $line .= ' | DATA: ' . Helpers::json($context);
        }

        $line .= PHP_EOL;

        file_put_contents(
            $this->logFile,
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
