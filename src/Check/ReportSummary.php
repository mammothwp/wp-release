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
     * @param AllowList     $allow    Allow-list (default: nothing allowed).
     */
    public function __construct(public readonly array $findings, private readonly AllowList $allow = new AllowList())
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
     * Number of WARNING findings that are not allowed.
     *
     * @return int
     */
    public function warnings(): int
    {
        return $this->count('WARNING') - count($this->allowed());
    }

    /**
     * Allowed findings with the rule that allowed each.
     *
     * @return list<array{finding: Finding, rule: string}>
     */
    public function allowed(): array
    {
        $allowed = [];
        foreach ($this->findings as $finding) {
            $rule = $this->allow->matchingRule($finding);
            if ($rule !== null) {
                $allowed[] = ['finding' => $finding, 'rule' => $rule];
            }
        }
        return $allowed;
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
        $lines = [$this->countsLine(), ''];
        foreach ($this->findings as $f) {
            if ($this->allow->matchingRule($f) !== null) {
                continue;
            }
            $where = $f->line > 0 ? sprintf('%s:%d:%d', $f->file, $f->line, $f->column) : $f->file;
            $lines[] = sprintf('[%s] %s  %s', $f->type, $where, $f->code);
            $lines[] = '    ' . $f->message;
        }
        foreach ($this->allowed() as $a) {
            $lines[] = self::allowedLine($a['finding'], $a['rule']);
        }
        $lines[] = '';
        $lines[] = 'By check code:';
        foreach ($this->byCode() as $key => $count) {
            $lines[] = sprintf('  %4d  %s', $count, $key);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * "Plugin Check: N error(s), M warning(s), K allowed".
     *
     * @return string
     */
    public function countsLine(): string
    {
        return sprintf('Plugin Check: %d error(s), %d warning(s), %d allowed', $this->errors(), $this->warnings(), count($this->allowed()));
    }

    /**
     * One "ALLOWED: ..." output line.
     *
     * @param Finding $finding Finding.
     * @param string  $rule    Allowing regex.
     * @return string
     */
    public static function allowedLine(Finding $finding, string $rule): string
    {
        return sprintf('ALLOWED: %s: %s (rule: %s)', $finding->code, $finding->message, $rule);
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
