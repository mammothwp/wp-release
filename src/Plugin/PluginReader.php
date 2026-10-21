<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Reads PluginInfo from a directory and detects plugin slugs.
 */
final class PluginReader
{
    /**
     * Read headers and readme of the plugin in $dir.
     *
     * @param string $dir  Plugin directory.
     * @param string $slug Plugin slug.
     * @return PluginInfo
     */
    public function read(string $dir, string $slug): PluginInfo
    {
        $main = $dir . '/' . $slug . '.php';
        $readmePath = $dir . '/readme.txt';
        $headers = is_file($main) ? HeaderParser::parse((string) file_get_contents($main)) : [];
        $readme = is_file($readmePath) ? ReadmeParser::parse((string) file_get_contents($readmePath)) : null;
        return new PluginInfo($slug, is_file($main), $headers, $readme);
    }

    /**
     * Find root-level PHP files carrying a "Plugin Name:" header and return their slugs.
     *
     * @param string $dir Plugin root.
     * @return list<string>
     */
    public function detectSlugs(string $dir): array
    {
        $slugs = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $headers = HeaderParser::parse((string) file_get_contents($file));
            if (isset($headers['plugin name'])) {
                $slugs[] = basename($file, '.php');
            }
        }
        return $slugs;
    }
}
