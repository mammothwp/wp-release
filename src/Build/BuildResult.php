<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Build;

/**
 * Outcome of a successful build.
 */
final class BuildResult
{
    /**
     * @param string $zipPath   Absolute zip path.
     * @param string $distDir   Absolute dist/<slug> path.
     * @param string $version   Plugin version.
     * @param int    $zipBytes  Zip size in bytes.
     * @param int    $fileCount Number of files in the zip.
     */
    public function __construct(
        public readonly string $zipPath,
        public readonly string $distDir,
        public readonly string $version,
        public readonly int $zipBytes,
        public readonly int $fileCount
    ) {
    }

    /**
     * Result as a flat array for output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'zip' => $this->zipPath,
            'version' => $this->version,
            'zip_size' => $this->humanSize(),
            'zip_bytes' => $this->zipBytes,
            'file_count' => $this->fileCount,
        ];
    }

    /**
     * Human readable zip size.
     *
     * @return string
     */
    private function humanSize(): string
    {
        return $this->zipBytes >= 1048576
            ? sprintf('%.2f MB', $this->zipBytes / 1048576)
            : sprintf('%.1f KB', $this->zipBytes / 1024);
    }
}
