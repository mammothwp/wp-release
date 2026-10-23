<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;

/**
 * Narrow allow-list for Plugin Check findings.
 *
 * Only WARNINGs can be allowed, and only when a rule matches "<code>: <message>". ERRORs are never allowed.
 * Rules are bare PCRE bodies (no delimiters); they are compiled with the `u` flag, inline flags such as `(?i)` work.
 */
final class AllowList
{
    /**
     * @param list<string> $rules Regex bodies without delimiters.
     */
    public function __construct(public readonly array $rules = [])
    {
        foreach ($rules as $rule) {
            if (@preg_match(self::compile($rule), '') === false) {
                throw new ReleaseException(sprintf('Invalid Plugin Check allow regex: %s', $rule), ExitCode::CONFIG);
            }
        }
    }

    /**
     * Split an env value (newline or `||` separated) into rules.
     *
     * @param string|null $value Raw env value.
     * @return list<string>
     */
    public static function splitEnv(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $parts = preg_split('/\R|\|\|/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== ''));
    }

    /**
     * The first rule that allows this finding, or null when it is not allowed.
     *
     * @param Finding $finding Finding.
     * @return string|null
     */
    public function matchingRule(Finding $finding): ?string
    {
        if ($finding->type !== 'WARNING') {
            return null;
        }
        $subject = $finding->code . ': ' . $finding->message;
        foreach ($this->rules as $rule) {
            if (preg_match(self::compile($rule), $subject) === 1) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Wrap a bare regex body in delimiters.
     *
     * @param string $rule Regex body.
     * @return string
     */
    private static function compile(string $rule): string
    {
        return '~' . str_replace('~', '\~', $rule) . '~u';
    }
}
