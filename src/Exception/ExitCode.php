<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Exception;

/**
 * Process exit codes returned by mmwp-release. Documented in README.md.
 */
final class ExitCode
{
    public const SUCCESS = 0;
    public const GENERAL = 1;
    public const CONFIG = 2;
    public const MISSING_TOOL = 3;
    public const BUILD = 10;
    public const VALIDATION = 11;
    public const PLUGIN_CHECK = 20;
    public const SITE_SETUP = 21;
    public const SELF_TEST = 22;
    public const DEPLOY_REFUSED = 30;
    public const SVN = 31;
    public const NOT_APPROVED = 32;
    public const GITHUB = 40;
}
