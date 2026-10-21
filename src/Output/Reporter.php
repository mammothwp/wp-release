<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Output;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes logs to stderr and results to stdout (table or JSON); masks registered secrets everywhere.
 */
class Reporter
{
    /** @var list<string> */
    private array $secrets = [];

    /**
     * @param OutputInterface $out    Stdout (results only).
     * @param OutputInterface $err    Stderr (logs).
     * @param bool            $debug  Verbose command output.
     * @param string          $format table|json.
     */
    public function __construct(
        private readonly OutputInterface $out,
        private readonly OutputInterface $err,
        private readonly bool $debug = false,
        private readonly string $format = 'table'
    ) {
    }

    /**
     * Register a value that must never appear in output.
     *
     * @param string|null $secret Secret value.
     * @return void
     */
    public function addSecret(?string $secret): void
    {
        if ($secret === null || strlen($secret) < 3) {
            return;
        }
        $this->secrets[] = $secret;
    }

    /**
     * Replace every registered secret in the text.
     *
     * @param string $text Text to sanitise.
     * @return string
     */
    public function mask(string $text): string
    {
        return $this->secrets === [] ? $text : str_replace($this->secrets, '***', $text);
    }

    /**
     * Log an informational line to stderr.
     *
     * @param string $message Message.
     * @return void
     */
    public function info(string $message): void
    {
        $this->err->writeln(OutputFormatter::escape($this->mask($message)));
    }

    /**
     * Log a line only in debug mode.
     *
     * @param string $message Message.
     * @return void
     */
    public function debug(string $message): void
    {
        if (!$this->debug) {
            return;
        }
        $this->info($message);
    }

    /**
     * Log an external command about to run.
     *
     * @param list<string> $command Command argv.
     * @return void
     */
    public function command(array $command): void
    {
        $this->info('$ ' . implode(' ', array_map('escapeshellarg', $command)));
    }

    /**
     * Whether debug mode is on.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Print a result set to stdout in the configured format.
     *
     * @param string               $title Heading (table mode only).
     * @param array<string, mixed> $data  Key/value result.
     * @return void
     */
    public function result(string $title, array $data): void
    {
        if ($this->format === 'json') {
            $this->out->writeln($this->mask((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
            return;
        }
        $table = new Table($this->out);
        $table->setHeaderTitle($title);
        $table->setHeaders(['Key', 'Value']);
        foreach ($data as $key => $value) {
            $table->addRow([$key, OutputFormatter::escape($this->mask($this->stringify($value)))]);
        }
        $table->render();
    }

    /**
     * Convert a result value to display text.
     *
     * @param mixed $value Value.
     * @return string
     */
    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if ($value === null) {
            return '-';
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
        }
        return is_scalar($value) ? (string) $value : '';
    }
}
