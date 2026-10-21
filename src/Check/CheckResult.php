<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

/**
 * Outcome of `check`; also the shape persisted to plugin-check.json.
 */
final class CheckResult
{
    /**
     * @param string        $slug     Plugin slug.
     * @param string        $version  Plugin version that was checked.
     * @param bool          $strict   Whether warnings counted as failures.
     * @param list<Finding> $findings Findings.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $version,
        public readonly bool $strict,
        public readonly array $findings
    ) {
    }

    /**
     * Summary helper.
     *
     * @return ReportSummary
     */
    public function summary(): ReportSummary
    {
        return new ReportSummary($this->findings);
    }

    /**
     * Whether the check passed under its own strictness.
     *
     * @return bool
     */
    public function passed(): bool
    {
        return $this->summary()->passes($this->strict);
    }

    /**
     * Array persisted as plugin-check.json.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $summary = $this->summary();
        return [
            'slug' => $this->slug,
            'version' => $this->version,
            'strict' => $this->strict,
            'errors' => $summary->errors(),
            'warnings' => $summary->warnings(),
            'by_code' => $summary->byCode(),
            'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $this->findings),
        ];
    }
}
