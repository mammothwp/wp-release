<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Parses the WordPress plugin header comment.
 */
final class HeaderParser
{
    private const FIELDS = [
        'plugin name',
        'version',
        'text domain',
        'requires php',
        'requires at least',
        'license',
        'description',
        'requires plugins',
    ];

    /**
     * Extract known headers from the first 8 KB of a PHP file.
     *
     * @param string $source File contents.
     * @return array<string, string> Lower-case header name => value.
     */
    public static function parse(string $source): array
    {
        $source = str_replace("\r", "\n", substr($source, 0, 8192));
        $headers = [];
        foreach (self::FIELDS as $field) {
            $pattern = '/^[ \t\/*#@]*' . preg_quote($field, '/') . ':(.*)$/mi';
            if (preg_match($pattern, $source, $match) !== 1) {
                continue;
            }
            $value = trim((string) preg_replace('/\s*\*\/.*$/', '', $match[1]));
            if ($value !== '') {
                $headers[$field] = $value;
            }
        }
        return $headers;
    }
}
