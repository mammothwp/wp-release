<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Config\ConfigLoader;
use MammothWP\WPRelease\Config\DependencyList;
use PHPUnit\Framework\TestCase;

/**
 * Dependency slug parsing and precedence.
 */
final class DependencyListTest extends TestCase
{
    /**
     * Parsing handles spaces, commas, empties and duplicates.
     *
     * @return void
     */
    public function testParse(): void
    {
        $this->assertSame([], DependencyList::parse(null));
        $this->assertSame([], DependencyList::parse(''));
        $this->assertSame([], DependencyList::parse(" , \n "));
        $this->assertSame(['woocommerce'], DependencyList::parse('woocommerce'));
        $this->assertSame(['a', 'b', 'c'], DependencyList::parse('a b,c'));
        $this->assertSame(['a', 'b'], DependencyList::parse(' a,, b  a '));
    }

    /**
     * --deps overrides MMWP_BROWSER_DEPS; unset means none.
     *
     * @return void
     */
    public function testConfigPrecedence(): void
    {
        $dir = sys_get_temp_dir() . '/mmwp-deps-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $load = static fn (array $o, array $e) => (new ConfigLoader())->load($o + ['path' => $dir, 'slug' => 'x'], $e)->deps;
            $this->assertSame([], $load([], []));
            $this->assertSame(['woocommerce'], $load([], ['MMWP_BROWSER_DEPS' => 'woocommerce']));
            $this->assertSame(['a', 'b'], $load(['deps' => 'a,b'], ['MMWP_BROWSER_DEPS' => 'woocommerce']));
        } finally {
            rmdir($dir);
        }
    }
}
