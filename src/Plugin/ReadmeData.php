<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Parsed wordpress.org readme.txt header fields.
 */
final class ReadmeData
{
    /**
     * @param array<string, string> $headers Lower-case header name => value.
     * @param string                $raw     Original file contents.
     */
    public function __construct(public readonly array $headers, public readonly string $raw)
    {
    }

    /**
     * Value of "Stable tag", if any.
     *
     * @return string|null
     */
    public function stableTag(): ?string
    {
        return $this->headers['stable tag'] ?? null;
    }

    /**
     * Value of "Tested up to", if any.
     *
     * @return string|null
     */
    public function testedUpTo(): ?string
    {
        return $this->headers['tested up to'] ?? null;
    }
}
