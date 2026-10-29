<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Build;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Plugin\PluginReader;
use MammothWP\WPRelease\Plugin\PluginValidator;
use MammothWP\WPRelease\Process\CommandRunner;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds dist/<slug>/ and <slug>.zip from a plugin repository without touching its working tree vendor/.
 */
class Builder
{
    /**
     * @param CommandRunner   $runner    Process wrapper.
     * @param Reporter        $reporter  Logger.
     * @param PluginReader    $reader    Plugin reader.
     * @param PluginValidator $validator Validator.
     * @param Filesystem      $fs        Filesystem helper.
     * @param StageFiles      $stageFiles Decides which files are staged.
     * @param VendorValidator $vendorValidator Checks runtime dependencies in the zip.
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly Reporter $reporter,
        private readonly PluginReader $reader = new PluginReader(),
        private readonly PluginValidator $validator = new PluginValidator(),
        private readonly Filesystem $fs = new Filesystem(),
        private readonly StageFiles $stageFiles = new StageFiles(),
        private readonly VendorValidator $vendorValidator = new VendorValidator()
    ) {
    }

    /**
     * Run the full build and validation.
     *
     * @param Config $config Run configuration.
     * @return BuildResult
     */
    public function build(Config $config): BuildResult
    {
        foreach (['rsync', 'zip', 'unzip'] as $tool) {
            $this->runner->requireTool($tool);
        }
        if (!is_file($config->path . '/.distignore')) {
            throw new ReleaseException('.distignore is required in the plugin root.', ExitCode::CONFIG);
        }
        $this->checkDistignore($config);
        $stage = $this->makeTempDir();
        try {
            $this->stage($config, $stage);
            $this->installDependencies($config, $stage);
            $manifest = $this->readManifest($stage);
            $this->syncDist($config, $stage);
        } finally {
            $this->fs->remove($stage);
        }
        $this->validateDist($config);
        $result = $this->zip($config);
        $this->validateVendor($config, $result, $manifest);
        $this->validateZip($config, $result);
        $this->reporter->info(sprintf('Built %s (%s, %d files)', basename($result->zipPath), $result->toArray()['zip_size'], $result->fileCount));
        return $result;
    }

    /**
     * Copy the shipped files to a temp dir: tracked files in a git work tree, a filtered copy otherwise.
     *
     * @param Config $config Config.
     * @param string $stage  Temp dir.
     * @return void
     */
    private function stage(Config $config, string $stage): void
    {
        $freshVendor = is_file($config->path . '/composer.json');
        $files = $this->stageFiles->tracked($this->runner, $config->path);
        if ($files === null) {
            $this->stageWithoutGit($config, $stage, $freshVendor);
            return;
        }
        $selected = $this->stageFiles->select($files, $config->slug, $freshVendor, $config->path);
        $list = $stage . '.files';
        file_put_contents($list, implode("\0", $selected));
        try {
            $this->runner->mustRun(['rsync', '-a', '--from0', '--files-from=' . $list, $config->path . '/', $stage . '/'], ExitCode::BUILD);
        } finally {
            $this->fs->remove($list);
        }
        // .distignore drives the next step even when it is not tracked.
        $this->fs->copy($config->path . '/.distignore', $stage . '/.distignore', true);
    }

    /**
     * Copy the whole working tree when there is no git, excluding dot-files, phars, caches and the tools dir.
     *
     * @param Config $config      Config.
     * @param string $stage       Temp dir.
     * @param bool   $freshVendor Whether vendor/ is rebuilt by composer.
     * @return void
     */
    private function stageWithoutGit(Config $config, string $stage, bool $freshVendor): void
    {
        $env = getenv('MMWP_TOOLS_DIR');
        $tools = $this->stageFiles->relativeToolsDir($env === false ? null : $env, $config->path);
        $command = ['rsync', '-a', '--include=/.distignore'];
        foreach ($this->stageFiles->fallbackExcludes($config->slug, $freshVendor, $tools) as $exclude) {
            $command[] = '--exclude=' . $exclude;
        }
        $this->runner->mustRun([...$command, $config->path . '/', $stage . '/'], ExitCode::BUILD);
    }

    /**
     * Fresh production composer install inside the temp copy.
     *
     * @param Config $config Config.
     * @param string $stage  Temp dir.
     * @return void
     */
    private function installDependencies(Config $config, string $stage): void
    {
        if (!is_file($stage . '/composer.json')) {
            return;
        }
        $this->runner->requireTool('composer');
        $this->runner->mustRun(
            ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress', '--prefer-dist'],
            ExitCode::BUILD,
            $stage
        );
    }

    /**
     * Rsync the staged tree into dist/<slug>/ honouring .distignore.
     *
     * @param Config $config Config.
     * @param string $stage  Temp dir.
     * @return void
     */
    private function syncDist(Config $config, string $stage): void
    {
        $dist = $config->distDir();
        $this->fs->remove($dist);
        $this->fs->mkdir($dist);
        $this->runner->mustRun([
            'rsync', '-a', '--delete', '--delete-excluded',
            '--exclude-from=' . $stage . '/.distignore',
            '--exclude=/.distignore', '--exclude=/plugin-check.json', '--exclude=/plugin-check.txt',
            $stage . '/', $dist . '/',
        ], ExitCode::BUILD);
    }

    /**
     * Validate headers and readme of the built tree.
     *
     * @param Config $config Config.
     * @return void
     */
    private function validateDist(Config $config): void
    {
        $info = $this->reader->read($config->distDir(), $config->slug);
        $problems = $this->validator->validate($info, $config->phpMin);
        if ($problems !== []) {
            throw new ReleaseException("Plugin validation failed:\n - " . implode("\n - ", $problems), ExitCode::VALIDATION);
        }
    }

    /**
     * Create the zip with <slug>/ as the single top-level directory.
     *
     * @param Config $config Config.
     * @return BuildResult
     */
    private function zip(Config $config): BuildResult
    {
        $zip = $config->zipPath();
        $this->fs->remove($zip);
        $this->runner->mustRun(['zip', '-qr', $zip, $config->slug], ExitCode::BUILD, dirname($config->distDir()));
        $files = $this->listZip($zip);
        $info = $this->reader->read($config->distDir(), $config->slug);
        return new BuildResult($zip, $config->distDir(), (string) $info->version(), (int) filesize($zip), count($files));
    }

    /**
     * List file entries of a zip (directories excluded).
     *
     * @param string $zip Zip path.
     * @return list<string>
     */
    private function listZip(string $zip): array
    {
        $out = $this->runner->mustRun(['unzip', '-Z1', $zip], ExitCode::BUILD)->stdout;
        $entries = array_filter(explode("\n", $out), static fn (string $line): bool => $line !== '' && !str_ends_with($line, '/'));
        return array_values($entries);
    }

    /**
     * Fail when the zip contains forbidden files.
     *
     * @param Config      $config Config.
     * @param BuildResult $result Build result.
     * @return void
     */
    private function validateZip(Config $config, BuildResult $result): void
    {
        $prefix = $config->slug . '/';
        $relative = array_map(
            static fn (string $path): string => str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path,
            $this->listZip($result->zipPath)
        );
        $finder = new ForbiddenFiles($config->excludes);
        $forbidden = array_values(array_unique([...$finder->find($relative), ...$finder->findHiddenOrPhar($relative)]));
        if ($forbidden === []) {
            return;
        }
        $shown = implode("\n - ", array_slice($forbidden, 0, 20));
        throw new ReleaseException("Forbidden files found in the zip (hidden files, phars or .distignore targets):\n - " . $shown, ExitCode::VALIDATION);
    }

    /**
     * Warn (fail when strict) about unanchored .distignore entries that also strip files inside vendor packages.
     *
     * @param Config $config Config.
     * @return void
     */
    private function checkDistignore(Config $config): void
    {
        $warnings = $this->vendorValidator->distignoreWarnings((string) file_get_contents($config->path . '/.distignore'));
        foreach ($warnings as $warning) {
            $this->reporter->info('WARNING: ' . $warning);
        }
        if ($warnings !== [] && $config->strict) {
            throw new ReleaseException("Strict mode: .distignore would strip files inside vendor/:\n - " . implode("\n - ", $warnings), ExitCode::VALIDATION);
        }
    }

    /**
     * Read the staged composer.json and composer.lock (after the install, so the lock always exists).
     *
     * @param string $stage Temp dir.
     * @return array{composer: array<string, mixed>|null, lock: array<string, mixed>|null}
     */
    private function readManifest(string $stage): array
    {
        return ['composer' => $this->readJson($stage . '/composer.json'), 'lock' => $this->readJson($stage . '/composer.lock')];
    }

    /**
     * Decode a JSON file to an array; null when missing or invalid.
     *
     * @param string $file File path.
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Fail when the zip lacks the runtime Composer dependencies the plugin declares.
     *
     * @param Config      $config   Config.
     * @param BuildResult $result   Build result.
     * @param array{composer: array<string, mixed>|null, lock: array<string, mixed>|null} $manifest Staged manifests.
     * @return void
     */
    private function validateVendor(Config $config, BuildResult $result, array $manifest): void
    {
        if ($manifest['composer'] === null) {
            return;
        }
        $prefix = $config->slug . '/';
        $relative = array_map(
            static fn (string $path): string => str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path,
            $this->listZip($result->zipPath)
        );
        $problems = $this->vendorValidator->problems($manifest['composer'], $manifest['lock'], $relative);
        if ($problems === []) {
            return;
        }
        throw new ReleaseException(
            "The zip is missing runtime Composer dependencies (check that .distignore does not strip vendor/):\n - " . implode("\n - ", $problems),
            ExitCode::VALIDATION
        );
    }

    /**
     * Create a unique temp directory.
     *
     * @return string
     */
    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/mmwp-build-' . bin2hex(random_bytes(6));
        $this->fs->mkdir($dir);
        return $dir;
    }
}
