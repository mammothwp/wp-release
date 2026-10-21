<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Header and readme data of a plugin directory.
 */
final class PluginInfo
{
    /**
     * @param string                $slug          Plugin slug.
     * @param bool                  $mainFileExists Whether <slug>.php exists.
     * @param array<string, string> $headers       Main file headers.
     * @param ReadmeData|null       $readme        Parsed readme.txt, null when missing.
     */
    public function __construct(
        public readonly string $slug,
        public readonly bool $mainFileExists,
        public readonly array $headers,
        public readonly ?ReadmeData $readme
    ) {
    }

    /**
     * Version from the plugin header.
     *
     * @return string|null
     */
    public function version(): ?string
    {
        return $this->headers['version'] ?? null;
    }
}
