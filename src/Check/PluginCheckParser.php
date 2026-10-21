<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

/**
 * Parses `wp plugin check --format=json` output, which is a series of "FILE: <path>" lines each
 * followed by a JSON array (a bare JSON array is accepted too).
 */
final class PluginCheckParser
{
    /**
     * Parse raw command output into findings.
     *
     * @param string $output Raw stdout.
     * @return list<Finding>
     */
    public static function parse(string $output): array
    {
        $findings = [];
        $file = '';
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'FILE: ')) {
                $file = substr($line, 6);
                continue;
            }
            if (!str_starts_with($line, '[')) {
                continue;
            }
            array_push($findings, ...self::decodeRows($line, $file));
        }
        return $findings;
    }

    /**
     * Decode one JSON array line.
     *
     * @param string $json JSON text.
     * @param string $file Current file.
     * @return list<Finding>
     */
    private static function decodeRows(string $json, string $file): array
    {
        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            return [];
        }
        $findings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = new Finding(
                $file,
                (int) ($row['line'] ?? 0),
                (int) ($row['column'] ?? 0),
                strtoupper((string) ($row['type'] ?? 'ERROR')),
                (string) ($row['code'] ?? ''),
                html_entity_decode((string) ($row['message'] ?? ''), ENT_QUOTES | ENT_HTML5)
            );
        }
        return $findings;
    }
}
