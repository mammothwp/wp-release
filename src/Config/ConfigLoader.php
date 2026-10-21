<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Config;

use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Plugin\PluginReader;

/**
 * Builds Config from CLI options, MMWP_* environment variables and mmwp-release.json.
 * Precedence: CLI flag > environment > mmwp-release.json > auto-detection.
 */
final class ConfigLoader
{
    /**
     * @param PluginReader $reader Used for slug auto-detection.
     */
    public function __construct(private readonly PluginReader $reader = new PluginReader())
    {
    }

    /**
     * Resolve configuration.
     *
     * @param array<string, mixed>  $options CLI options (null/false when absent).
     * @param array<string, string> $env     Environment variables.
     * @return Config
     */
    public function load(array $options, array $env): Config
    {
        $path = $this->resolvePath((string) ($options['path'] ?? ''));
        $file = $this->readFile($path);
        $slug = $this->firstString([$options['slug'] ?? null, $env['MMWP_SLUG'] ?? null, $file['slug'] ?? null]);
        $slug = $slug ?? $this->detectSlug($path);
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            throw new ReleaseException(sprintf('Invalid plugin slug "%s".', $slug), ExitCode::CONFIG);
        }
        $format = (string) ($options['format'] ?? 'table');
        if (!in_array($format, ['table', 'json'], true)) {
            throw new ReleaseException('--format must be table or json.', ExitCode::CONFIG);
        }
        $db = (string) ($this->firstString([$options['db'] ?? null, $env['MMWP_DB'] ?? null]) ?? 'sqlite');
        if (!in_array($db, ['sqlite', 'mysql'], true)) {
            throw new ReleaseException('--db must be sqlite or mysql.', ExitCode::CONFIG);
        }
        return new Config(
            path: $path,
            slug: $slug,
            dryRun: (bool) ($options['dry-run'] ?? false),
            debug: (bool) ($options['debug-mode'] ?? false) || self::bool($env['MMWP_DEBUG'] ?? null),
            force: (bool) ($options['force'] ?? false),
            format: $format,
            strict: (bool) ($options['strict'] ?? false) || self::bool($env['MMWP_PCP_STRICT'] ?? null, (bool) ($file['strict'] ?? false)),
            phpMin: $this->firstString([$file['php_min'] ?? null]),
            wpVersion: $this->firstString([$env['WP_VERSION'] ?? null, $file['wp_version'] ?? null]) ?? 'latest',
            excludes: $this->stringList($file['excludes'] ?? []),
            db: $db,
            dbEnv: [
                'host' => $env['MMWP_DB_HOST'] ?? 'localhost',
                'user' => $env['MMWP_DB_USER'] ?? 'root',
                'pass' => $env['MMWP_DB_PASS'] ?? '',
                'name' => $env['MMWP_DB_NAME'] ?? '',
            ],
            selfTest: (bool) ($options['self-test'] ?? false) || self::bool($env['MMWP_SELF_TEST'] ?? null),
            keep: (bool) ($options['keep'] ?? false),
            failIfUnapproved: (bool) ($options['fail-if-unapproved'] ?? false) || self::bool($env['MMWP_FAIL_IF_UNAPPROVED'] ?? null),
            githubOwner: $this->firstString([$env['MMWP_GITHUB_OWNER'] ?? null]) ?? 'mammothwp',
            githubRepo: $this->firstString([$env['MMWP_GITHUB_REPO'] ?? null, $file['github_repo'] ?? null]) ?? ''
        );
    }

    /**
     * Parse a boolean env value.
     *
     * @param string|null $value   Raw value.
     * @param bool        $default Value when unset.
     * @return bool
     */
    public static function bool(?string $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Absolute, existing plugin root.
     *
     * @param string $path Value of --path (may be empty).
     * @return string
     */
    private function resolvePath(string $path): string
    {
        $real = realpath($path === '' ? (string) getcwd() : $path);
        if ($real === false || !is_dir($real)) {
            throw new ReleaseException(sprintf('Plugin path "%s" does not exist.', $path), ExitCode::CONFIG);
        }
        return $real;
    }

    /**
     * Read mmwp-release.json if present.
     *
     * @param string $path Plugin root.
     * @return array<string, mixed>
     */
    private function readFile(string $path): array
    {
        $file = $path . '/mmwp-release.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new ReleaseException('mmwp-release.json is not valid JSON.', ExitCode::CONFIG);
        }
        return $data;
    }

    /**
     * Detect the slug from the main plugin file.
     *
     * @param string $path Plugin root.
     * @return string
     */
    private function detectSlug(string $path): string
    {
        $slugs = $this->reader->detectSlugs($path);
        if (count($slugs) === 1) {
            return $slugs[0];
        }
        $hint = $slugs === [] ? 'no file with a "Plugin Name:" header found' : 'several candidates: ' . implode(', ', $slugs);
        throw new ReleaseException(sprintf('Cannot determine the plugin slug (%s). Use --slug or MMWP_SLUG.', $hint), ExitCode::CONFIG);
    }

    /**
     * First non-empty string of a list.
     *
     * @param list<mixed> $values Candidates.
     * @return string|null
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    /**
     * Normalise a JSON value to a list of strings.
     *
     * @param mixed $value Raw value.
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
