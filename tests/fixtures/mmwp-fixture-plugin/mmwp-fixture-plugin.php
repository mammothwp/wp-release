<?php
/**
 * Plugin Name:       MMWP Fixture Plugin
 * Plugin URI:        https://mammothwp.com
 * Description:       Tiny valid plugin used to test mammothwp/wp-release.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mammoth WP
 * Author URI:        https://mammothwp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mmwp-fixture-plugin
 *
 * @package MmwpFixturePlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print a greeting in the footer.
 *
 * @return void
 */
function mmwp_fixture_plugin_footer() {
	echo esc_html__( 'Hello from the fixture plugin.', 'mmwp-fixture-plugin' );
}
add_action( 'wp_footer', 'mmwp_fixture_plugin_footer' );
