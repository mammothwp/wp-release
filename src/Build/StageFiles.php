<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Build;

use MammothWP\WPRelease\Process\CommandRunner;

/**
 * Decides which files of a plugin working tree are copied into the build stage.
 *
 * In a git work tree only tracked files are eligible, so CI leftovers (caches, downloaded phars, tool dirs) can
 * never reach the zip. Without git the whole tree is copied with a fixed exclude list.
 */
final class StageFiles
{
    private const ALWAYS_EXCLUDED = ['node_modules', 'dist'];

    /**
     * List the files tracked by git in the plugin root, or null when the path is not a git work tree.
     *
     * @param CommandRunner $runner Process wrapper.
     * @param string        $path   Plugin root.
     * @return list<string>|null Paths relative to the plugin root, or null for "no git".
     */
    public function tracked(CommandRunner $runner, string $path): ?array
    {
        $inside = $runner->run(['git', 'rev-parse', '--is-inside-work-tree'], $path);
        if (!$inside->isSuccessful() || trim($inside->stdout) !== 'true') {
            return null;
        }
        $list = $runner->run(['git', 'ls-files', '-z', '--cached', '--full-name', '--', '.'], $path);
        if (!$list->isSuccessful()) {
            return null;
        }
        $top = $runner->run(['git', 'rev-parse', '--show-prefix'], $path);
        $prefix = trim($top->stdout);
        $paths = [];
        foreach (explode("\0", $list->stdout) as $entry) {
            if ($entry === '' || ($prefix !== '' && !str_starts_with($entry, $prefix))) {
                continue;
            }
            $paths[] = substr($entry, strlen($prefix));
        }
        return $paths === [] ? null : $paths;
    }

    /**
     * Narrow a tracked file list to what may be staged: existing files only, no VCS/build output, no vendor/ when
     * composer installs it fresh.
     *
     * @param list<string> $tracked      Tracked paths relative to the plugin root.
     * @param string       $slug         Plugin slug (its zip is never staged).
     * @param bool         $freshVendor  Whether vendor/ is rebuilt by composer.
     * @param string       $root         Plugin root, used to drop tracked-but-deleted files.
     * @return list<string>
     */
    public function select(array $tracked, string $slug, bool $freshVendor, string $root = ''): array
    {
        $selected = [];
        foreach ($tracked as $path) {
            if ($this->isExcluded($path, $slug, $freshVendor)) {
                continue;
            }
            if ($root !== '' && !file_exists($root . '/' . $path)) {
                continue;
            }
            $selected[] = $path;
        }
        return $selected;
    }

    /**
     * Rsync exclude patterns for the no-git fallback: VCS data, dependencies, build output, every dot-file or
     * dot-dir at any level, phars, caches and the CI tools dir.
     *
     * @param string      $slug        Plugin slug.
     * @param bool        $freshVendor Whether vendor/ is rebuilt by composer.
     * @param string|null $toolsDir    Tools dir relative to the plugin root (e.g. from MMWP_TOOLS_DIR), if inside it.
     * @return list<string>
     */
    public function fallbackExcludes(string $slug, bool $freshVendor, ?string $toolsDir = null): array
    {
        $excludes = ['.*', '*.phar', '.cache/', '/node_modules', '/dist', '/' . $slug . '.zip'];
        if ($freshVendor) {
            $excludes[] = '/vendor';
        }
        if ($toolsDir !== null && $toolsDir !== '') {
            $excludes[] = '/' . trim($toolsDir, '/') . '/';
        }
        return $excludes;
    }

    /**
     * Resolve a tools dir (absolute or relative) to a path relative to the plugin root, or null when outside it.
     *
     * @param string|null $toolsDir Raw value, e.g. $MMWP_TOOLS_DIR.
     * @param string      $root     Plugin root.
     * @return string|null
     */
    public function relativeToolsDir(?string $toolsDir, string $root): ?string
    {
        if ($toolsDir === null || trim($toolsDir, '/. ') === '') {
            return null;
        }
        $root = rtrim($root, '/') . '/';
        if (str_starts_with($toolsDir, '/')) {
            return str_starts_with($toolsDir, $root) ? trim(substr($toolsDir, strlen($root)), '/') : null;
        }
        return trim(preg_replace('#^\./#', '', $toolsDir) ?? '', '/');
    }

    /**
     * Whether a tracked path is never staged.
     *
     * @param string $path        Relative path.
     * @param string $slug        Plugin slug.
     * @param bool   $freshVendor Whether vendor/ is rebuilt by composer.
     * @return bool
     */
    private function isExcluded(string $path, string $slug, bool $freshVendor): bool
    {
        if ($path === $slug . '.zip') {
            return true;
        }
        $top = explode('/', $path)[0];
        if (in_array($top, self::ALWAYS_EXCLUDED, true)) {
            return true;
        }
        return $freshVendor && $top === 'vendor';
    }
}
