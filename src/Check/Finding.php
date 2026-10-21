<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

/**
 * One Plugin Check result row.
 */
final class Finding
{
    /**
     * @param string $file    File relative to the plugin.
     * @param int    $line    Line number (0 when n/a).
     * @param int    $column  Column (0 when n/a).
     * @param string $type    ERROR or WARNING.
     * @param string $code    Check code.
     * @param string $message Decoded message.
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly int $column,
        public readonly string $type,
        public readonly string $code,
        public readonly string $message
    ) {
    }

    /**
     * Array form for JSON output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'type' => $this->type,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
