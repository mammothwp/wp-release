<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

/**
 * Aggregates findings and decides pass/fail.
 */
final class ReportSummary
{
    /**
     * @param list<Finding> $findings Findings.
     */
    public function __construct(public readonly array $findings)
    {
    }

    /**
     * Number of ERROR findings.
     *
     * @return int
     */
    public function errors(): int
    {
        return $this->count('ERROR');
    }

    /**
     * Number of WARNING findings.
     *
     * @return int
     */
    public function warnings(): int
    {
        return $this->count('WARNING');
    }

    /**
     * Whether the report passes.
     *
     * @param bool $strict Treat warnings as failures.
     * @return bool
     */
    public function passes(bool $strict): bool
    {
        return $this->errors() === 0 && (!$strict || $this->warnings() === 0);
    }

    /**
     * Counts keyed by "TYPE code", sorted by count descending.
     *
     * @return array<string, int>
     */
    public function byCode(): array
    {
        $counts = [];
        foreach ($this->findings as $finding) {
            $key = $finding->type . ' ' . $finding->code;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }

    /**
     * Human readable report (plugin-check.txt).
     *
     * @return string
     */
    public function toText(): string
    {
        $lines = [sprintf('Plugin Check: %d error(s), %d warning(s)', $this->errors(), $this->warnings()), ''];
        foreach ($this->findings as $f) {
            $where = $f->line > 0 ? sprintf('%s:%d:%d', $f->file, $f->line, $f->column) : $f->file;
            $lines[] = sprintf('[%s] %s  %s', $f->type, $where, $f->code);
            $lines[] = '    ' . $f->message;
        }
        $lines[] = '';
        $lines[] = 'By check code:';
        foreach ($this->byCode() as $key => $count) {
            $lines[] = sprintf('  %4d  %s', $count, $key);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Count findings of one type.
     *
     * @param string $type ERROR or WARNING.
     * @return int
     */
    private function count(string $type): int
    {
        return count(array_filter($this->findings, static fn (Finding $f): bool => $f->type === $type));
    }
}
