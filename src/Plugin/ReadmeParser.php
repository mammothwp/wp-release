<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Parses readme.txt headers (the block between the title and the first section).
 */
final class ReadmeParser
{
    /**
     * Parse readme contents.
     *
     * @param string $source readme.txt contents.
     * @return ReadmeData
     */
    public static function parse(string $source): ReadmeData
    {
        $headers = [];
        $lines = preg_split('/\R/', $source) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/^==[^=].*==\s*$/', $line) === 1) {
                break;
            }
            if (preg_match('/^([A-Za-z ]+):\s*(.+?)\s*$/', $line, $match) === 1) {
                $headers[strtolower(trim($match[1]))] = $match[2];
            }
        }
        return new ReadmeData($headers, $source);
    }
}
