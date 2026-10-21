<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Pure validation of plugin headers and readme against wordpress.org requirements.
 */
final class PluginValidator
{
    /**
     * Return a list of human readable problems (empty when valid).
     *
     * @param PluginInfo  $info   Plugin data.
     * @param string|null $phpMin Minimum acceptable "Requires PHP" header, if configured.
     * @return list<string>
     */
    public function validate(PluginInfo $info, ?string $phpMin = null): array
    {
        $problems = $this->validateHeaders($info, $phpMin);
        $readme = $info->readme;
        if ($readme === null) {
            return [...$problems, 'readme.txt is missing.'];
        }
        return [...$problems, ...$this->validateReadme($info, $readme)];
    }

    /**
     * Validate main file headers.
     *
     * @param PluginInfo  $info   Plugin data.
     * @param string|null $phpMin Minimum "Requires PHP".
     * @return list<string>
     */
    private function validateHeaders(PluginInfo $info, ?string $phpMin): array
    {
        if (!$info->mainFileExists) {
            return [sprintf('Main plugin file %s.php does not exist.', $info->slug)];
        }
        $problems = [];
        $h = $info->headers;
        foreach (['version' => 'Version', 'requires php' => 'Requires PHP', 'requires at least' => 'Requires at least'] as $key => $label) {
            if (!isset($h[$key])) {
                $problems[] = sprintf('Header "%s" is missing in %s.php.', $label, $info->slug);
            }
        }
        if (($h['text domain'] ?? null) !== $info->slug) {
            $problems[] = sprintf('Header "Text Domain" must equal the slug "%s" (found "%s").', $info->slug, $h['text domain'] ?? '');
        }
        if (stripos($h['license'] ?? '', 'gpl') === false) {
            $problems[] = 'Header "License" must be GPL compatible (found "' . ($h['license'] ?? '') . '").';
        }
        if ($phpMin !== null && isset($h['requires php']) && version_compare($h['requires php'], $phpMin, '<')) {
            $problems[] = sprintf('Header "Requires PHP" (%s) is below the configured minimum %s.', $h['requires php'], $phpMin);
        }
        return $problems;
    }

    /**
     * Validate readme.txt fields and consistency with the header.
     *
     * @param PluginInfo $info   Plugin data.
     * @param ReadmeData $readme Readme data.
     * @return list<string>
     */
    private function validateReadme(PluginInfo $info, ReadmeData $readme): array
    {
        $problems = [];
        if ($readme->stableTag() === null) {
            $problems[] = 'readme.txt has no "Stable tag".';
        }
        if ($readme->testedUpTo() === null) {
            $problems[] = 'readme.txt has no "Tested up to".';
        }
        $version = $info->version();
        if ($readme->stableTag() !== null && $version !== null && $readme->stableTag() !== $version) {
            $problems[] = sprintf('Version mismatch: header Version %s != readme Stable tag %s.', $version, $readme->stableTag());
        }
        return $problems;
    }
}
