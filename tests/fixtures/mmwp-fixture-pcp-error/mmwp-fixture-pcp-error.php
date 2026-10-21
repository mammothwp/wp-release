<?php
/**
 * Plugin Name:       MMWP Fixture Pcp Error
 * Plugin URI:        https://mammothwp.com
 * Description:       Valid headers but an unescaped echo (Plugin Check ERROR), used to test mammothwp/wp-release.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mammoth WP
 * Author URI:        https://mammothwp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mmwp-fixture-pcp-error
 *
 * @package MmwpFixturePcpError
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print a greeting in the footer.
 *
 * @return void
 */
function mmwp_fixture_pcp_error_footer() {
	echo $_GET['name']; // Deliberately unescaped.
}
add_action( 'wp_footer', 'mmwp_fixture_pcp_error_footer' );
