<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;

/**
 * Decides whether a stored plugin-check.json permits a deploy.
 */
final class CheckGate
{
    /**
     * Throw DEPLOY_REFUSED unless the report is for this slug/version and has no blocking findings.
     *
     * @param array<string, mixed>|null $report  Decoded plugin-check.json (null when missing).
     * @param string                    $slug    Plugin slug.
     * @param string                    $version Version about to be released.
     * @param bool                      $strict  Whether warnings block.
     * @return void
     */
    public function assertPasses(?array $report, string $slug, string $version, bool $strict): void
    {
        $reason = $this->refusalReason($report, $slug, $version, $strict);
        if ($reason === null) {
            return;
        }
        throw new ReleaseException('Deploy refused: ' . $reason . ' Run `mmwp-release check` first (or use --force).', ExitCode::DEPLOY_REFUSED);
    }

    /**
     * Reason the report does not permit a deploy, or null when it does.
     *
     * @param array<string, mixed>|null $report  Decoded report.
     * @param string                    $slug    Slug.
     * @param string                    $version Version.
     * @param bool                      $strict  Strict mode.
     * @return string|null
     */
    public function refusalReason(?array $report, string $slug, string $version, bool $strict): ?string
    {
        if ($report === null) {
            return 'no plugin-check.json found.';
        }
        if (($report['slug'] ?? null) !== $slug || ($report['version'] ?? null) !== $version) {
            return sprintf('plugin-check.json is for a different plugin/version (%s %s).', (string) ($report['slug'] ?? '?'), (string) ($report['version'] ?? '?'));
        }
        if ((int) ($report['errors'] ?? 1) > 0) {
            return sprintf('plugin-check.json reports %d error(s).', (int) $report['errors']);
        }
        if ($strict && (int) ($report['warnings'] ?? 1) > 0) {
            return sprintf('strict mode: plugin-check.json reports %d warning(s).', (int) $report['warnings']);
        }
        return null;
    }
}
